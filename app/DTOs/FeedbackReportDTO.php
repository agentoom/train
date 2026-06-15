<?php

namespace App\DTOs;

/**
 * Aggregated output of the Phase 1.6 feedback pipeline for one evaluation report.
 *
 * @property-read array<GenerationControlSignalDTO> $signals
 */
final class FeedbackReportDTO
{
    /**
     * @param  array<GenerationControlSignalDTO>  $signals
     */
    public function __construct(
        public readonly int $evaluationReportId,
        public readonly FailureAnalysisDTO $failureAnalysis,
        public readonly array $signals,
        public readonly float $systemHealthScore,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'evaluation_run_id' => $this->evaluationReportId,
            'failure_analysis' => $this->failureAnalysis->toArray(),
            'improvement_signals' => array_map(fn (GenerationControlSignalDTO $s) => $s->toArray(), $this->signals),
            'system_health_score' => $this->systemHealthScore,
        ];
    }
}
