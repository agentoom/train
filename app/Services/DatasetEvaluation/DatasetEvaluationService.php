<?php

namespace App\Services\DatasetEvaluation;

use App\DTOs\BatchEvaluationResultDTO;
use App\DTOs\DatasetBatchDTO;
use App\Models\DatasetEvaluationReport;
use App\Models\DatasetReleaseVersion;
use App\Models\DatasetVersion;
use App\Services\DatasetEvaluation\Feedback\DatasetEvaluationFeedbackService;
use App\Services\DatasetEvaluation\Evaluators\ConversationQualityEvaluator;
use App\Services\DatasetEvaluation\Evaluators\DistributionDriftEvaluator;
use App\Services\DatasetEvaluation\Evaluators\EdgeCaseEvaluator;
use App\Services\DatasetEvaluation\Evaluators\NegativeRatioEvaluator;
use App\Services\DatasetEvaluation\Evaluators\TaskPerformanceEvaluator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Main orchestrator for the Dataset Evaluation & Governance Layer (Phase 1.5).
 *
 * Accepts a DatasetVersion as input (read-only), runs the evaluation pipeline,
 * persists the evaluation report, and optionally produces a release version.
 */
class DatasetEvaluationService
{
    private EvaluationPipeline $pipeline;

    public function __construct()
    {
        $this->pipeline = new EvaluationPipeline([
            new TaskPerformanceEvaluator(),
            new ConversationQualityEvaluator(),
            new DistributionDriftEvaluator(),
            new NegativeRatioEvaluator(),
            new EdgeCaseEvaluator(),
        ]);
    }

    /**
     * Evaluate a dataset version and persist the evaluation report.
     *
     * @param  array<string, mixed>  $metadata  Optional metadata (e.g. baseline stats for drift comparison)
     */
    public function evaluate(DatasetVersion $version, array $metadata = []): DatasetEvaluationReport
    {
        Log::info('DatasetEvaluationService: starting evaluation', [
            'version_id' => $version->id,
            'dataset_project_id' => $version->dataset_project_id,
        ]);

        $batch = $this->buildBatch($version, $metadata);
        $result = $this->pipeline->run($batch);

        $report = $this->persistReport($version, $result);

        Log::info('DatasetEvaluationService: evaluation complete', [
            'version_id' => $version->id,
            'overall_score' => $result->overallScore,
            'passed' => $result->passed,
        ]);

        // Phase 1.6: optionally run the feedback & control layer.
        // Disabled by default. Enable via config: dataset_evaluation.feedback_enabled = true
        if (config('dataset_evaluation.feedback_enabled', false)) {
            app(DatasetEvaluationFeedbackService::class)->process($report);
        }

        return $report;
    }

    /**
     * Compare two dataset versions and return the evaluation reports for both.
     *
     * @param  array<string, mixed>  $metadata
     * @return array{baseline: DatasetEvaluationReport, candidate: DatasetEvaluationReport, improved: bool}
     */
    public function compare(DatasetVersion $baseline, DatasetVersion $candidate, array $metadata = []): array
    {
        $baselineReport = $this->evaluate($baseline, $metadata);
        $candidateReport = $this->evaluate($candidate, array_merge($metadata, [
            'baseline_duplicate_rate' => $baselineReport->duplicate_rate,
        ]));

        $improved = $candidateReport->overall_score > $baselineReport->overall_score;

        Log::info('DatasetEvaluationService: version comparison complete', [
            'baseline_version_id' => $baseline->id,
            'candidate_version_id' => $candidate->id,
            'baseline_score' => $baselineReport->overall_score,
            'candidate_score' => $candidateReport->overall_score,
            'improved' => $improved,
        ]);

        return [
            'baseline' => $baselineReport,
            'candidate' => $candidateReport,
            'improved' => $improved,
        ];
    }

    /**
     * Promote a passing evaluation report to a named release version.
     */
    public function release(DatasetEvaluationReport $report, string $releaseTag, ?string $notes = null): DatasetReleaseVersion
    {
        if (! $report->passed) {
            throw new \RuntimeException(
                "Cannot release dataset version {$report->dataset_version_id}: evaluation did not pass."
            );
        }

        $release = DatasetReleaseVersion::create([
            'dataset_evaluation_report_id' => $report->id,
            'dataset_version_id' => $report->dataset_version_id,
            'dataset_project_id' => $report->datasetVersion->dataset_project_id,
            'release_tag' => $releaseTag,
            'overall_score' => $report->overall_score,
            'notes' => $notes,
        ]);

        Log::info('DatasetEvaluationService: release version created', [
            'release_id' => $release->id,
            'release_tag' => $releaseTag,
            'version_id' => $report->dataset_version_id,
            'overall_score' => $report->overall_score,
        ]);

        return $release;
    }

    /**
     * Build a DatasetBatchDTO from a DatasetVersion by loading its rows read-only.
     * Project-level configuration is merged into metadata so evaluators can read it
     * without coupling directly to the model relationship.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function buildBatch(DatasetVersion $version, array $metadata): DatasetBatchDTO
    {
        $rows = $version->rows()
            ->get()
            ->map(fn ($row) => array_merge($row->toArray(), ['payload' => $row->payload ?? []]))
            ->values();

        $project = $version->datasetProject;

        $projectMetadata = $project ? [
            'conversation_enabled' => (bool) $project->conversation_enabled,
            'negative_example_ratio' => (int) $project->negative_example_ratio,
            'dataset_type' => $project->dataset_type ?? '',
        ] : [];

        return new DatasetBatchDTO(
            version: $version,
            rows: Collection::make($rows),
            schema: $version->schema_snapshot,
            metadata: array_merge($projectMetadata, $metadata),
        );
    }

    /**
     * Persist the evaluation result as a DatasetEvaluationReport.
     */
    private function persistReport(DatasetVersion $version, BatchEvaluationResultDTO $result): DatasetEvaluationReport
    {
        $evaluatorScores = array_map(
            fn ($r) => ['score' => $r->score, 'passed' => $r->passed, 'summary' => $r->summary, 'details' => $r->details],
            $result->evaluatorResults,
        );

        $duplicateRate = $result->evaluatorResults['distribution_drift']->details['duplicate_rate'] ?? 0.0;

        return DatasetEvaluationReport::create([
            'dataset_version_id' => $version->id,
            'overall_score' => $result->overallScore,
            'passed' => $result->passed,
            'verdict' => $result->verdict,
            'evaluator_scores' => $evaluatorScores,
            'metadata' => $result->metadata,
            'duplicate_rate' => $duplicateRate,
        ]);
    }
}
