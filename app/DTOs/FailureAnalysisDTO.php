<?php

namespace App\DTOs;

/**
 * Result of the FailureAnalysisEngine for a single evaluation report.
 *
 * @property-read array<string, array<string, mixed>> $failuresByType
 * @property-read array<string>                       $systemic
 * @property-read array<string, mixed>                $rawEvaluatorScores
 */
final class FailureAnalysisDTO
{
    /**
     * @param  array<string, array<string, mixed>>  $failuresByType  Failures grouped by issue category
     * @param  array<string>  $systemic  Issue categories detected as systemic
     * @param  array<string, mixed>  $rawEvaluatorScores
     */
    public function __construct(
        public readonly array $failuresByType,
        public readonly array $systemic,
        public readonly array $rawEvaluatorScores,
    ) {}

    public function hasFailures(): bool
    {
        return count($this->failuresByType) > 0;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'failures_by_type' => $this->failuresByType,
            'systemic_issues' => $this->systemic,
            'evaluator_scores' => $this->rawEvaluatorScores,
        ];
    }
}
