<?php

namespace App\Services\Evaluation;

use App\DTOs\EvaluationResultDTO;
use App\Models\AIProvider;
use App\Models\DatasetProject;
use App\Services\AI\InferenceExecutionService;
use Illuminate\Support\Facades\Log;
use Throwable;

class DatasetEvaluationService
{
    public function __construct(
        private readonly InferenceExecutionService $inferenceService,
        private readonly EvaluationPromptBuilderService $promptBuilder,
    ) {}

    /**
     * Evaluate a single dataset row using the configured evaluation provider.
     *
     * Returns null if evaluation is disabled or the provider is not configured.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $schema
     */
    public function evaluate(array $row, ?array $schema, DatasetProject $project): ?EvaluationResultDTO
    {
        if (! $project->evaluation_enabled) {
            return null;
        }

        $provider = $this->resolveProvider($project);

        if (! $provider) {
            Log::warning('DatasetEvaluationService: evaluation enabled but no provider configured', [
                'project_id' => $project->id,
            ]);

            return null;
        }

        try {
            $prompt = $this->promptBuilder->build($row, $schema, $project);
            $result = $this->inferenceService->execute($provider, $prompt);

            return $this->parseResult($result->rawContent ?? json_encode($result->rows[0] ?? []));
        } catch (Throwable $e) {
            Log::warning('DatasetEvaluationService: evaluation failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            return new EvaluationResultDTO(
                score: 0,
                reasoning: 'Evaluation failed: '.$e->getMessage(),
                issues: ['evaluation_error'],
            );
        }
    }

    /**
     * Check whether a row passes the minimum quality threshold.
     */
    public function passes(EvaluationResultDTO $result, DatasetProject $project): bool
    {
        return $result->score >= ($project->minimum_quality_score ?? 75);
    }

    private function resolveProvider(DatasetProject $project): ?AIProvider
    {
        if ($project->evaluation_ai_provider_id) {
            return $project->evaluationAiProvider;
        }

        return $project->aiProvider;
    }

    /**
     * Parse the raw LLM JSON response into an EvaluationResultDTO.
     */
    private function parseResult(string $raw): EvaluationResultDTO
    {
        $raw = trim($raw);

        // Strip markdown code fences if present
        if (str_starts_with($raw, '```')) {
            $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw) ?? $raw;
            $raw = preg_replace('/\s*```$/', '', $raw) ?? $raw;
        }

        $data = json_decode($raw, true);

        if (! is_array($data)) {
            return new EvaluationResultDTO(
                score: 0,
                reasoning: 'Could not parse evaluation response.',
                issues: ['parse_error'],
            );
        }

        $score = max(0, min(100, (int) ($data['score'] ?? 0)));
        $reasoning = (string) ($data['reasoning'] ?? '');
        $issues = array_values(array_filter((array) ($data['issues'] ?? []), 'is_string'));

        return new EvaluationResultDTO(
            score: $score,
            reasoning: $reasoning,
            issues: $issues,
        );
    }
}
