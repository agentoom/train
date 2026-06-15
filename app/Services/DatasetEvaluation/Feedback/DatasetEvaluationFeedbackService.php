<?php

namespace App\Services\DatasetEvaluation\Feedback;

use App\DTOs\FeedbackReportDTO;
use App\Models\DatasetEvaluationReport;
use App\Models\DatasetFeedbackReport;
use Illuminate\Support\Facades\Log;

/**
 * Main orchestrator for the Dataset Evaluation Feedback & Control Layer (Phase 1.6).
 *
 * Consumes a DatasetEvaluationReport from Phase 1.5 and produces:
 *   - structured failure analysis
 *   - actionable improvement signals
 *   - a persisted feedback report
 *
 * This service is purely analytical — it never modifies dataset generation.
 *
 * Usage:
 *   DatasetEvaluationFeedbackService::process($evaluationReport);
 */
class DatasetEvaluationFeedbackService
{
    public function __construct(
        private readonly FailureAnalysisEngine $failureAnalysisEngine,
        private readonly DatasetImprovementSignalBuilder $signalBuilder,
        private readonly FeedbackReportRepository $repository,
    ) {}

    /**
     * Process an evaluation report and persist the feedback output.
     *
     * This is the primary entry point for Phase 1.6.
     */
    public function process(DatasetEvaluationReport $report): DatasetFeedbackReport
    {
        Log::info('DatasetEvaluationFeedbackService: starting feedback processing', [
            'evaluation_report_id' => $report->id,
            'overall_score' => $report->overall_score,
            'passed' => $report->passed,
        ]);

        $failureAnalysis = $this->failureAnalysisEngine->analyse($report);
        $signals = $this->signalBuilder->build($failureAnalysis, $report);
        $systemHealthScore = $this->computeSystemHealthScore($report, count($signals));

        $dto = new FeedbackReportDTO(
            evaluationReportId: $report->id,
            failureAnalysis: $failureAnalysis,
            signals: $signals,
            systemHealthScore: $systemHealthScore,
        );

        $feedbackReport = $this->repository->store($dto);

        Log::info('DatasetEvaluationFeedbackService: feedback processing complete', [
            'evaluation_report_id' => $report->id,
            'feedback_report_id' => $feedbackReport->id,
            'systemic_issues' => $failureAnalysis->systemic,
            'signal_count' => count($signals),
            'system_health_score' => $systemHealthScore,
        ]);

        return $feedbackReport;
    }

    /**
     * Compute a system health score (0–100) based on the overall evaluation score
     * and the number of improvement signals emitted.
     *
     * The score is deterministic and traceable to measurable metrics.
     */
    private function computeSystemHealthScore(DatasetEvaluationReport $report, int $signalCount): float
    {
        $base = (float) $report->overall_score;

        // Each signal represents a detected problem; penalise proportionally.
        $penalty = min(30.0, $signalCount * 5.0);

        return max(0.0, round($base - $penalty, 1));
    }
}
