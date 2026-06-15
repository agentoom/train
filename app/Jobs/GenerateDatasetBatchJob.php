<?php

namespace App\Jobs;

use App\Enums\BatchStatus;
use App\Models\DatasetProjectLog;
use App\Models\DatasetRow;
use App\Models\GenerationBatch;
use App\Models\GenerationUsage;
use App\Services\AI\InferenceExecutionService;
use App\Services\Dataset\AugmentationPromptBuilderService;
use App\Services\Dataset\ConversationPromptBuilderService;
use App\Services\Dataset\DatasetSourceParsingService;
use App\Services\Dataset\DatasetProgressService;
use App\Services\Dataset\DatasetValidationService;
use App\Services\Dataset\HashDeduplicationService;
use App\Services\Dataset\NegativeExampleService;
use App\Services\Dataset\PromptBuilderService;
use App\Services\Dataset\SemanticDeduplicationService;
use App\Services\Evaluation\DatasetEvaluationService;
use App\Services\Pipeline\CriticService;
use App\Services\Pipeline\RefinerService;
use App\Support\Cost\CostEstimator;
use App\Support\Json\JsonRepairer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateDatasetBatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * Get the timeout for the job.
     *
     * Accounts for the initial inference call plus up to MAX_DUPLICATE_ATTEMPTS
     * replacement calls, each bounded by the configured LLM timeout.
     */
    public function timeout(): int
    {
        $llmTimeout = (int) \App\Models\Setting::get('llm_timeout', 90);

        return $llmTimeout * (self::MAX_DUPLICATE_ATTEMPTS + 2) + 60;
    }

    /** Maximum replacement generation attempts to prevent infinite loops. */
    private const MAX_DUPLICATE_ATTEMPTS = 5;

    /**
     * If this fraction of rows returned in a replacement attempt are duplicates,
     * the dataset is considered saturated and the loop stops early.
     */
    private const DUPLICATE_SATURATION_THRESHOLD = 0.8;

    public function __construct(public readonly int $batchId) {}

    public function handle(
        InferenceExecutionService $inferenceService,
        PromptBuilderService $promptBuilder,
        ConversationPromptBuilderService $conversationPromptBuilder,
        DatasetValidationService $validator,
        DatasetProgressService $progressService,
        JsonRepairer $jsonRepairer,
        CostEstimator $costEstimator,
        HashDeduplicationService $hashDedup,
        SemanticDeduplicationService $semanticDedup,
        DatasetEvaluationService $evaluationService,
        CriticService $criticService,
        RefinerService $refinerService,
        NegativeExampleService $negativeExampleService,
        AugmentationPromptBuilderService $augmentationPromptBuilder,
        DatasetSourceParsingService $sourceParsingService,
    ): void {
        $batch = GenerationBatch::with('datasetVersion.datasetProject.aiProvider')->find($this->batchId);

        if (! $batch) {
            Log::warning('GenerateDatasetBatchJob: batch not found', ['batch_id' => $this->batchId]);

            return;
        }

        // Idempotency: skip if already completed or cancelled
        if ($batch->status === BatchStatus::Cancelled || $batch->cancelled_at !== null) {
            Log::info('GenerateDatasetBatchJob: skipping cancelled batch', ['batch_id' => $batch->id]);

            return;
        }

        if (in_array($batch->status, [BatchStatus::Completed, BatchStatus::Cancelled])) {
            Log::info('GenerateDatasetBatchJob: skipping already-finished batch', ['batch_id' => $batch->id]);

            return;
        }

        $version = $batch->datasetVersion;
        $project = $version?->datasetProject;

        if (! $version || ! $project) {
            Log::error('GenerateDatasetBatchJob: missing version or project', ['batch_id' => $batch->id]);

            return;
        }

        $provider = $project->aiProvider;

        if (! $provider) {
            $progressService->markBatchFailed($batch, 'No AI provider configured for this project.');

            return;
        }

        // Capture reproducibility snapshots before execution
        $batch->update([
            'provider_snapshot' => [
                'id' => $provider->id,
                'label' => $provider->label,
                'type' => $provider->type->value,
                'default_model' => $provider->default_model,
                'base_url' => $provider->base_url,
            ],
            'model_snapshot' => $project->model,
        ]);

        $progressService->markBatchRunning($batch);

        try {
            $totalBatches = GenerationBatch::where('dataset_version_id', $version->id)->count();

            $conversationEnabled = (bool) ($project->conversation_enabled ?? false);
            $augmentationEnabled = (bool) ($project->augmentation_enabled ?? false);

            if ($augmentationEnabled) {
                $sourceRows = $this->loadSourceRows($project, $sourceParsingService);
                $prompt = $augmentationPromptBuilder->buildFromVersion($version, $sourceRows, $batch->batch_number - 1, $batch->limit, $totalBatches);
            } elseif ($conversationEnabled) {
                $prompt = $conversationPromptBuilder->buildFromVersion($version, $batch->batch_number, $batch->limit, $totalBatches);
            } else {
                $prompt = $promptBuilder->buildFromVersion($version, $batch->batch_number, $batch->limit, $totalBatches);
            }

            $batch->update(['prompt_snapshot' => $prompt->systemPrompt]);

            Log::info('GenerateDatasetBatchJob: executing inference', [
                'batch_id' => $batch->id,
                'provider_id' => $provider->id,
                'batch_number' => $batch->batch_number,
            ]);

            $result = $inferenceService->execute($provider, $prompt);

            $rows = $result->rows;

            if (empty($rows)) {
                Log::info('GenerateDatasetBatchJob: inference returned empty rows', [
                    'batch_id' => $batch->id,
                    'has_raw_content' => ! empty($result->rawContent),
                ]);
            }

            // Attempt JSON repair if rows are empty but raw content exists
            if (empty($rows) && $result->rawContent) {
                $repaired = $jsonRepairer->repair($result->rawContent);
                if ($repaired !== null) {
                    $rows = is_array($repaired) && isset($repaired[0]) && is_array($repaired[0])
                        ? $repaired
                        : [$repaired];

                    Log::info('GenerateDatasetBatchJob: JSON repair successful', [
                        'batch_id' => $batch->id,
                        'rows_count' => count($rows),
                    ]);
                } else {
                    Log::warning('GenerateDatasetBatchJob: JSON repair failed', [
                        'batch_id' => $batch->id,
                        'raw_content_preview' => substr($result->rawContent, 0, 200),
                    ]);
                }
            }

            $schema = $version->schema_snapshot;
            $rowIndex = $batch->offset;
            $savedCount = 0;
            $duplicatesDetected = 0;
            $regenerated = 0;
            $targetCount = $batch->limit;

            $semanticEnabled = (bool) $project->semantic_deduplication_enabled;
            $semanticThreshold = (float) ($project->semantic_similarity_threshold ?? 0.92);
            $replacementEnabled = (bool) $project->replacement_generation_enabled;
            $evaluationEnabled = (bool) $project->evaluation_enabled;
            $evaluationRejected = 0;

            // Process initial rows
            $accepted = $this->processRows(
                rows: $rows,
                version: $version,
                batch: $batch,
                schema: $schema,
                validator: $validator,
                hashDedup: $hashDedup,
                semanticDedup: $semanticDedup,
                semanticEnabled: $semanticEnabled,
                semanticThreshold: $semanticThreshold,
                rowIndex: $rowIndex,
                savedCount: $savedCount,
                duplicatesDetected: $duplicatesDetected,
                evaluationRejected: $evaluationRejected,
                evaluationService: $evaluationService,
                criticService: $criticService,
                refinerService: $refinerService,
                project: $project,
                conversationEnabled: $conversationEnabled,
            );

            $savedCount = $accepted['savedCount'];
            $rowIndex = $accepted['rowIndex'];
            $duplicatesDetected = $accepted['duplicatesDetected'];
            $evaluationRejected = $accepted['evaluationRejected'];

            // Replacement generation loop (also covers evaluation-rejected rows when enabled)
            $needsReplacement = $replacementEnabled && ($savedCount < $targetCount);
            $needsEvalReplacement = $evaluationEnabled && $replacementEnabled && $evaluationRejected > 0;

            if ($needsReplacement || $needsEvalReplacement) {
                $attempts = 0;

                while ($savedCount < $targetCount && $attempts < self::MAX_DUPLICATE_ATTEMPTS) {
                    $attempts++;
                    $needed = $targetCount - $savedCount;

                    Log::info('GenerateDatasetBatchJob: replacement generation attempt', [
                        'batch_id' => $batch->id,
                        'attempt' => $attempts,
                        'needed' => $needed,
                    ]);

                    $replacementPrompt = $promptBuilder->buildFromVersion($version, $batch->batch_number, $needed, $totalBatches);
                    $replacementResult = $inferenceService->execute($provider, $replacementPrompt);
                    $replacementRows = $replacementResult->rows;

                    if (empty($replacementRows)) {
                        Log::info('GenerateDatasetBatchJob: replacement inference returned empty rows', [
                            'batch_id' => $batch->id,
                            'attempt' => $attempts,
                            'has_raw_content' => ! empty($replacementResult->rawContent),
                        ]);
                    }

                    if (empty($replacementRows) && $replacementResult->rawContent) {
                        $repaired = $jsonRepairer->repair($replacementResult->rawContent);
                        if ($repaired !== null) {
                            $replacementRows = is_array($repaired) && isset($repaired[0]) && is_array($repaired[0])
                                ? $repaired
                                : [$repaired];

                            Log::info('GenerateDatasetBatchJob: replacement JSON repair successful', [
                                'batch_id' => $batch->id,
                                'attempt' => $attempts,
                                'rows_count' => count($replacementRows),
                            ]);
                        } else {
                            Log::warning('GenerateDatasetBatchJob: replacement JSON repair failed', [
                                'batch_id' => $batch->id,
                                'attempt' => $attempts,
                                'raw_content_preview' => substr($replacementResult->rawContent, 0, 200),
                            ]);
                        }
                    }

                    if (empty($replacementRows)) {
                        break;
                    }

                    $beforeSaved = $savedCount;
                    $beforeDuplicates = $duplicatesDetected;

                    $replacementAccepted = $this->processRows(
                        rows: $replacementRows,
                        version: $version,
                        batch: $batch,
                        schema: $schema,
                        validator: $validator,
                        hashDedup: $hashDedup,
                        semanticDedup: $semanticDedup,
                        semanticEnabled: $semanticEnabled,
                        semanticThreshold: $semanticThreshold,
                        rowIndex: $rowIndex,
                        savedCount: $savedCount,
                        duplicatesDetected: $duplicatesDetected,
                        evaluationRejected: $evaluationRejected,
                        evaluationService: $evaluationService,
                        criticService: $criticService,
                        refinerService: $refinerService,
                        project: $project,
                        conversationEnabled: $conversationEnabled,
                    );

                    $savedCount = $replacementAccepted['savedCount'];
                    $rowIndex = $replacementAccepted['rowIndex'];
                    $duplicatesDetected = $replacementAccepted['duplicatesDetected'];
                    $evaluationRejected = $replacementAccepted['evaluationRejected'];
                    $regenerated += $replacementAccepted['savedCount'] - $beforeSaved;

                    // No progress made — stop to avoid infinite loop
                    if ($savedCount === $beforeSaved) {
                        Log::info('GenerateDatasetBatchJob: replacement loop stopped — no progress', [
                            'batch_id' => $batch->id,
                            'attempt' => $attempts,
                        ]);
                        break;
                    }

                    // Dataset is saturated — stop if duplicate rate is too high
                    $newDuplicates = $duplicatesDetected - $beforeDuplicates;
                    $attemptTotal = $newDuplicates + ($savedCount - $beforeSaved);
                    if ($attemptTotal > 0 && ($newDuplicates / $attemptTotal) >= self::DUPLICATE_SATURATION_THRESHOLD) {
                        Log::info('GenerateDatasetBatchJob: replacement loop stopped — dataset saturated', [
                            'batch_id' => $batch->id,
                            'attempt' => $attempts,
                            'duplicate_rate' => round($newDuplicates / $attemptTotal, 2),
                        ]);
                        break;
                    }
                }
            }

            $partiallyCompleted = $savedCount < $targetCount;

            // Generate negative examples based on the configured ratio
            $negativeCount = $negativeExampleService->negativeCountForBatch($targetCount, $project);
            $negativesGenerated = 0;

            if ($negativeCount > 0) {
                $positiveRows = DatasetRow::where('generation_batch_id', $batch->id)
                    ->where('is_duplicate', false)
                    ->where('is_valid', true)
                    ->where('expected_behavior', 'correct')
                    ->inRandomOrder()
                    ->limit($negativeCount)
                    ->get();

                foreach ($positiveRows as $sourceRow) {
                    $negativeResult = $negativeExampleService->generate(
                        $sourceRow->payload,
                        $schema,
                        $project,
                    );

                    if ($negativeResult === null) {
                        continue;
                    }

                    $contentHash = $hashDedup->hash($negativeResult->row);

                    DatasetRow::create([
                        'dataset_version_id' => $version->id,
                        'generation_batch_id' => $batch->id,
                        'row_index' => $rowIndex++,
                        'payload' => $negativeResult->row,
                        'is_valid' => true,
                        'content_hash' => $contentHash,
                        'is_duplicate' => false,
                        'expected_behavior' => 'incorrect',
                        'failure_reason' => $negativeResult->failureReason,
                        'generated_by' => $project->model ?? null,
                    ]);

                    $negativesGenerated++;
                }

                if ($negativesGenerated > 0) {
                    Log::info('GenerateDatasetBatchJob: negative examples generated', [
                        'batch_id' => $batch->id,
                        'requested' => $negativeCount,
                        'generated' => $negativesGenerated,
                    ]);
                }
            }

            GenerationUsage::create([
                'dataset_project_id' => $project->id,
                'dataset_version_id' => $version->id,
                'generation_batch_id' => $batch->id,
                'ai_provider_id' => $provider->id,
                'model' => $prompt->model,
                'prompt_tokens' => $result->promptTokens,
                'completion_tokens' => $result->completionTokens,
                'total_tokens' => $result->totalTokens,
                'estimated_cost' => $costEstimator->estimate($prompt->model, $result->promptTokens, $result->completionTokens),
                'latency_ms' => $result->latencyMs,
            ]);

            $batch->update([
                'tokens_used' => $result->totalTokens,
                'duplicates_detected' => $duplicatesDetected,
                'regenerated' => $regenerated,
                'partially_completed' => $partiallyCompleted,
            ]);

            Log::info('GenerateDatasetBatchJob: batch completed', [
                'batch_id' => $batch->id,
                'rows_saved' => $savedCount,
                'duplicates_detected' => $duplicatesDetected,
                'regenerated' => $regenerated,
                'evaluation_rejected' => $evaluationRejected,
                'partially_completed' => $partiallyCompleted,
                'tokens' => $result->totalTokens,
            ]);

            if ($partiallyCompleted) {
                DatasetProjectLog::create([
                    'dataset_project_id' => $project->id,
                    'generation_batch_id' => $batch->id,
                    'level' => 'warning',
                    'message' => "Batch #{$batch->batch_number} completed partially.",
                    'details' => [
                        'requested' => $targetCount,
                        'saved' => $savedCount,
                        'duplicates_detected' => $duplicatesDetected,
                        'regenerated' => $regenerated,
                        'tokens' => $result->totalTokens,
                    ],
                ]);
            }

            $progressService->markBatchCompleted($batch->fresh());
        } catch (Throwable $e) {
            Log::error('GenerateDatasetBatchJob: batch failed', [
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
            ]);

            DatasetProjectLog::create([
                'dataset_project_id' => $project->id,
                'generation_batch_id' => $batch->id,
                'level' => 'error',
                'message' => "Batch #{$batch->batch_number} failed.",
                'details' => [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ],
            ]);

            $progressService->markBatchFailed($batch, $e->getMessage());
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('GenerateDatasetBatchJob failed', [
            'batch_id' => $this->batchId,
            'error' => $exception->getMessage(),
        ]);

        $batch = GenerationBatch::find($this->batchId);
        if ($batch) {
            app(DatasetProgressService::class)->markBatchFailed($batch, $exception->getMessage());
        }
    }

    /**
     * Process a set of rows: validate, deduplicate, evaluate, and persist accepted rows.
     *
     * @param  array<mixed>  $rows
     * @param  array<string, mixed>|null  $schema
     * @return array{savedCount: int, rowIndex: int, duplicatesDetected: int, evaluationRejected: int}
     */
    private function processRows(
        array $rows,
        mixed $version,
        GenerationBatch $batch,
        ?array $schema,
        DatasetValidationService $validator,
        HashDeduplicationService $hashDedup,
        SemanticDeduplicationService $semanticDedup,
        bool $semanticEnabled,
        float $semanticThreshold,
        int $rowIndex,
        int $savedCount,
        int $duplicatesDetected,
        int $evaluationRejected,
        DatasetEvaluationService $evaluationService,
        CriticService $criticService,
        RefinerService $refinerService,
        mixed $project,
        bool $conversationEnabled = false,
    ): array {
        foreach ($rows as $rowData) {
            if (! is_array($rowData)) {
                continue;
            }

            // In conversation mode, the LLM returns {"messages": [...]}
            // Extract messages for storage and use them as the dedup/validation target
            $messages = null;
            $turnCount = null;
            if ($conversationEnabled) {
                $messages = is_array($rowData['messages'] ?? null) ? $rowData['messages'] : null;
                if ($messages === null) {
                    continue;
                }
                $turnCount = (int) floor(count(array_filter($messages, fn ($m) => ($m['role'] ?? '') === 'user')));
                // Use messages as the payload for hashing/dedup; keep full rowData as payload
                $hashTarget = ['messages' => $messages];
            } else {
                $hashTarget = $rowData;
            }

            $contentHash = $hashDedup->hash($hashTarget);
            $isHashDuplicate = $hashDedup->isDuplicate($version->id, $contentHash);

            $isSemanticDuplicate = false;
            if (! $isHashDuplicate && $semanticEnabled) {
                $isSemanticDuplicate = $semanticDedup->isSemanticallyDuplicate($version->id, $hashTarget, $semanticThreshold);
            }

            $isDuplicate = $isHashDuplicate || $isSemanticDuplicate;

            if ($isDuplicate) {
                $duplicatesDetected++;

                // Persist as duplicate for audit trail
                DatasetRow::create([
                    'dataset_version_id' => $version->id,
                    'generation_batch_id' => $batch->id,
                    'row_index' => $rowIndex++,
                    'payload' => $rowData,
                    'is_valid' => false,
                    'content_hash' => $contentHash,
                    'is_duplicate' => true,
                    'messages' => $messages,
                    'turn_count' => $turnCount,
                ]);

                continue;
            }

            $isValid = $validator->validate($rowData, $schema);

            // Multi-stage pipeline: Critic → Refiner
            $generatedBy = $project->model ?? null;
            $criticFeedback = null;
            $refinedBy = null;

            $criticResult = $criticService->critique($rowData, $schema, $project);

            if ($criticResult !== null) {
                $criticFeedback = $criticResult->feedback;

                if ($criticResult->needsRefinement) {
                    Log::info('GenerateDatasetBatchJob: row flagged for refinement', [
                        'batch_id' => $batch->id,
                    ]);

                    $refinerResult = $refinerService->refine($rowData, $criticResult, $schema, $project);

                    if ($refinerResult !== null) {
                        $rowData = $refinerResult->refinedRow;
                        $refinedBy = $refinerResult->model;
                        // Recompute hash for refined row
                        $contentHash = $hashDedup->hash($rowData);
                        $isValid = $validator->validate($rowData, $schema);

                        Log::info('GenerateDatasetBatchJob: row refined', [
                            'batch_id' => $batch->id,
                            'refined_by' => $refinedBy,
                        ]);
                    }
                }
            }

            // LLM-as-a-judge quality evaluation
            $evaluationResult = $evaluationService->evaluate($rowData, $schema, $project);
            $evaluationPassed = $evaluationResult === null || $evaluationService->passes($evaluationResult, $project);
            $evaluationFailed = $evaluationResult !== null && ! $evaluationPassed;

            if ($evaluationFailed) {
                $evaluationRejected++;

                Log::info('GenerateDatasetBatchJob: row rejected by evaluator', [
                    'batch_id' => $batch->id,
                    'score' => $evaluationResult->score,
                    'minimum' => $project->minimum_quality_score ?? 75,
                ]);
            }

            DatasetRow::create([
                'dataset_version_id' => $version->id,
                'generation_batch_id' => $batch->id,
                'row_index' => $rowIndex++,
                'payload' => $rowData,
                'is_valid' => $isValid && ! $evaluationFailed,
                'content_hash' => $contentHash,
                'is_duplicate' => false,
                'quality_score' => $evaluationResult?->score,
                'quality_reasoning' => $evaluationResult?->reasoning,
                'quality_issues' => $evaluationResult?->issues ?: null,
                'evaluation_failed' => $evaluationFailed,
                'generated_by' => $generatedBy,
                'critic_feedback' => $criticFeedback,
                'refined_by' => $refinedBy,
                'expected_behavior' => 'correct',
                'messages' => $messages,
                'turn_count' => $turnCount,
                'source_similarity_score' => null,
            ]);

            if (! $evaluationFailed) {
                $savedCount++;
            }
        }

        return compact('savedCount', 'rowIndex', 'duplicatesDetected', 'evaluationRejected');
    }

    /**
     * Load source rows for augmentation from the project's uploaded sources.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadSourceRows(
        \App\Models\DatasetProject $project,
        DatasetSourceParsingService $parsingService,
    ): array {
        $sources = $project->sources()->orderBy('id')->get();

        if ($sources->isEmpty()) {
            return [];
        }

        $allRows = [];

        foreach ($sources as $source) {
            $rows = $parsingService->loadRows($source->file_path, $source->source_type);
            $allRows = array_merge($allRows, $rows);
        }

        return $allRows;
    }
}
