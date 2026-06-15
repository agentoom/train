<?php

namespace App\Services\DatasetEvaluation\Feedback;

use App\DTOs\FailureAnalysisDTO;
use App\Models\DatasetEvaluationReport;

/**
 * Analyses evaluation results and detects root causes of dataset failures.
 *
 * Groups failures by type, detects patterns across evaluators, and classifies
 * systemic issues. All logic is deterministic — no LLM reasoning involved.
 */
class FailureAnalysisEngine
{
    /** Evaluator score below this threshold is considered a failure. */
    private const FAILURE_THRESHOLD = 60.0;

    /** Minimum number of failing evaluators to classify an issue as systemic. */
    private const SYSTEMIC_MIN_EVALUATORS = 2;

    /**
     * Issue category → evaluator keys that contribute to it.
     *
     * @var array<string, array<string>>
     */
    private const CATEGORY_MAP = [
        'data_quality' => ['task_performance'],
        'conversation_structure_issues' => ['conversation_quality'],
        'distribution_drift' => ['distribution_drift'],
        'negative_ratio_imbalance' => ['negative_ratio'],
        'edge_case_scarcity' => ['edge_case'],
    ];

    public function analyse(DatasetEvaluationReport $report): FailureAnalysisDTO
    {
        /** @var array<string, array<string, mixed>> $scores */
        $scores = $report->evaluator_scores ?? [];

        $failuresByType = $this->groupFailuresByType($scores);
        $systemic = $this->detectSystemicIssues($failuresByType, $scores);

        return new FailureAnalysisDTO(
            failuresByType: $failuresByType,
            systemic: $systemic,
            rawEvaluatorScores: $scores,
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $scores
     * @return array<string, array<string, mixed>>
     */
    private function groupFailuresByType(array $scores): array
    {
        $failures = [];

        foreach (self::CATEGORY_MAP as $category => $evaluatorKeys) {
            foreach ($evaluatorKeys as $key) {
                if (! isset($scores[$key])) {
                    continue;
                }

                $evaluatorScore = (float) ($scores[$key]['score'] ?? 100.0);
                $passed = (bool) ($scores[$key]['passed'] ?? true);

                if (! $passed || $evaluatorScore < self::FAILURE_THRESHOLD) {
                    $failures[$category][] = [
                        'evaluator' => $key,
                        'score' => $evaluatorScore,
                        'summary' => $scores[$key]['summary'] ?? '',
                        'details' => $scores[$key]['details'] ?? [],
                    ];
                }
            }
        }

        return $failures;
    }

    /**
     * A category is systemic when at least SYSTEMIC_MIN_EVALUATORS evaluators
     * contribute to it, or when the overall report score is critically low.
     *
     * @param  array<string, array<string, mixed>>  $failuresByType
     * @param  array<string, array<string, mixed>>  $scores
     * @return array<string>
     */
    private function detectSystemicIssues(array $failuresByType, array $scores): array
    {
        $systemic = [];

        $failingEvaluatorCount = count(array_filter(
            $scores,
            fn ($s) => ! ($s['passed'] ?? true),
        ));

        foreach ($failuresByType as $category => $entries) {
            if (
                count($entries) >= self::SYSTEMIC_MIN_EVALUATORS
                || $failingEvaluatorCount >= self::SYSTEMIC_MIN_EVALUATORS
            ) {
                $systemic[] = $category;
            }
        }

        return array_values(array_unique($systemic));
    }
}
