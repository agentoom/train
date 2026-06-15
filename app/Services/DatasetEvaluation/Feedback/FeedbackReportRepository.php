<?php

namespace App\Services\DatasetEvaluation\Feedback;

use App\DTOs\FeedbackReportDTO;
use App\Models\DatasetFeedbackReport;

/**
 * Persists and retrieves Phase 1.6 feedback reports.
 */
class FeedbackReportRepository
{
    public function store(FeedbackReportDTO $dto): DatasetFeedbackReport
    {
        return DatasetFeedbackReport::create([
            'evaluation_run_id' => $dto->evaluationReportId,
            'failure_analysis_json' => $dto->failureAnalysis->toArray(),
            'improvement_signals_json' => array_map(
                fn ($signal) => $signal->toArray(),
                $dto->signals,
            ),
            'system_health_score' => $dto->systemHealthScore,
        ]);
    }

    public function findByEvaluationReportId(int $evaluationReportId): ?DatasetFeedbackReport
    {
        return DatasetFeedbackReport::where('evaluation_run_id', $evaluationReportId)->latest()->first();
    }
}
