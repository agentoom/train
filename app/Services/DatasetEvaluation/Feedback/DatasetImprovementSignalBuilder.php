<?php

namespace App\Services\DatasetEvaluation\Feedback;

use App\DTOs\FailureAnalysisDTO;
use App\DTOs\GenerationControlSignalDTO;
use App\Enums\SignalPriority;
use App\Enums\SignalType;
use App\Enums\TargetComponent;
use App\Models\DatasetEvaluationReport;

/**
 * Converts failure analysis into structured, deterministic improvement signals.
 *
 * Every signal maps to a measurable evaluator metric. No LLM reasoning is used.
 */
class DatasetImprovementSignalBuilder
{
    /** Score gap below which a signal is emitted for a given evaluator. */
    private const SIGNAL_THRESHOLD = 65.0;

    /** Maximum strength value for any signal. */
    private const MAX_STRENGTH = 1.0;

    /**
     * Build improvement signals from a failure analysis and the raw report.
     *
     * @return array<GenerationControlSignalDTO>
     */
    public function build(FailureAnalysisDTO $analysis, DatasetEvaluationReport $report): array
    {
        $signals = [];
        $scores = $analysis->rawEvaluatorScores;

        $signals = array_merge($signals, $this->negativeRatioSignals($scores));
        $signals = array_merge($signals, $this->edgeCaseSignals($scores));
        $signals = array_merge($signals, $this->conversationQualitySignals($scores));
        $signals = array_merge($signals, $this->distributionDriftSignals($scores, $report));
        $signals = array_merge($signals, $this->taskPerformanceSignals($scores));

        return $signals;
    }

    /**
     * @param  array<string, array<string, mixed>>  $scores
     * @return array<GenerationControlSignalDTO>
     */
    private function negativeRatioSignals(array $scores): array
    {
        $signals = [];
        $entry = $scores['negative_ratio'] ?? null;

        if ($entry === null) {
            return $signals;
        }

        $score = (float) ($entry['score'] ?? 100.0);
        $details = $entry['details'] ?? [];
        $ratio = (float) ($details['negative_ratio'] ?? 0.0);

        if ($score < self::SIGNAL_THRESHOLD) {
            if ($ratio < 0.05) {
                $signals[] = new GenerationControlSignalDTO(
                    signalType: SignalType::IncreaseNegativeSampling,
                    strength: $this->strengthFromGap($score),
                    targetComponent: TargetComponent::Generation,
                    priority: $score < 40 ? SignalPriority::High : SignalPriority::Medium,
                    explanation: "NegativeRatioEvaluator score {$score}/100 — negative ratio {$ratio} is below minimum threshold.",
                );
            } elseif ($ratio > 0.40) {
                $signals[] = new GenerationControlSignalDTO(
                    signalType: SignalType::DecreaseNegativeSampling,
                    strength: $this->strengthFromGap($score),
                    targetComponent: TargetComponent::Generation,
                    priority: SignalPriority::Medium,
                    explanation: "NegativeRatioEvaluator score {$score}/100 — negative ratio {$ratio} exceeds maximum threshold.",
                );
            }
        }

        return $signals;
    }

    /**
     * @param  array<string, array<string, mixed>>  $scores
     * @return array<GenerationControlSignalDTO>
     */
    private function edgeCaseSignals(array $scores): array
    {
        $signals = [];
        $entry = $scores['edge_case'] ?? null;

        if ($entry === null) {
            return $signals;
        }

        $score = (float) ($entry['score'] ?? 100.0);

        if ($score < self::SIGNAL_THRESHOLD) {
            $signals[] = new GenerationControlSignalDTO(
                signalType: SignalType::InjectEdgeCases,
                strength: $this->strengthFromGap($score),
                targetComponent: TargetComponent::Augmentation,
                priority: $score < 40 ? SignalPriority::High : SignalPriority::Medium,
                explanation: "EdgeCaseEvaluator score {$score}/100 — diversity or critic feedback coverage is too low.",
            );
        }

        return $signals;
    }

    /**
     * @param  array<string, array<string, mixed>>  $scores
     * @return array<GenerationControlSignalDTO>
     */
    private function conversationQualitySignals(array $scores): array
    {
        $signals = [];
        $entry = $scores['conversation_quality'] ?? null;

        if ($entry === null) {
            return $signals;
        }

        $score = (float) ($entry['score'] ?? 100.0);

        if ($score < self::SIGNAL_THRESHOLD) {
            $signals[] = new GenerationControlSignalDTO(
                signalType: SignalType::ImproveConversationStructure,
                strength: $this->strengthFromGap($score),
                targetComponent: TargetComponent::Refiner,
                priority: $score < 40 ? SignalPriority::High : SignalPriority::Medium,
                explanation: "ConversationQualityEvaluator score {$score}/100 — message structure or turn counts are inadequate.",
            );
        }

        return $signals;
    }

    /**
     * @param  array<string, array<string, mixed>>  $scores
     * @return array<GenerationControlSignalDTO>
     */
    private function distributionDriftSignals(array $scores, DatasetEvaluationReport $report): array
    {
        $signals = [];
        $entry = $scores['distribution_drift'] ?? null;

        if ($entry === null) {
            return $signals;
        }

        $score = (float) ($entry['score'] ?? 100.0);
        $duplicateRate = (float) ($report->duplicate_rate ?? 0.0);

        if ($score < self::SIGNAL_THRESHOLD) {
            if ($duplicateRate > 0.10) {
                $signals[] = new GenerationControlSignalDTO(
                    signalType: SignalType::ReduceDuplicates,
                    strength: min(self::MAX_STRENGTH, round($duplicateRate * 2, 2)),
                    targetComponent: TargetComponent::Generation,
                    priority: $duplicateRate > 0.25 ? SignalPriority::High : SignalPriority::Medium,
                    explanation: "DistributionDriftEvaluator score {$score}/100 — duplicate rate {$duplicateRate} exceeds acceptable limit.",
                );
            } else {
                $signals[] = new GenerationControlSignalDTO(
                    signalType: SignalType::IncreaseDataDiversity,
                    strength: $this->strengthFromGap($score),
                    targetComponent: TargetComponent::Augmentation,
                    priority: SignalPriority::Medium,
                    explanation: "DistributionDriftEvaluator score {$score}/100 — field coverage or distribution has drifted.",
                );
            }
        }

        return $signals;
    }

    /**
     * @param  array<string, array<string, mixed>>  $scores
     * @return array<GenerationControlSignalDTO>
     */
    private function taskPerformanceSignals(array $scores): array
    {
        $signals = [];
        $entry = $scores['task_performance'] ?? null;

        if ($entry === null) {
            return $signals;
        }

        $score = (float) ($entry['score'] ?? 100.0);

        if ($score < self::SIGNAL_THRESHOLD) {
            $signals[] = new GenerationControlSignalDTO(
                signalType: SignalType::ReviewGenerationPrompt,
                strength: $this->strengthFromGap($score),
                targetComponent: TargetComponent::Generation,
                priority: $score < 40 ? SignalPriority::High : SignalPriority::Medium,
                explanation: "TaskPerformanceEvaluator score {$score}/100 — validity or quality scores indicate generation prompt needs review.",
            );

            if ($score < 50) {
                $signals[] = new GenerationControlSignalDTO(
                    signalType: SignalType::TightenCriticThreshold,
                    strength: $this->strengthFromGap($score),
                    targetComponent: TargetComponent::Critic,
                    priority: SignalPriority::Low,
                    explanation: "TaskPerformanceEvaluator score {$score}/100 — consider tightening critic minimum score to filter low-quality rows earlier.",
                );
            }
        }

        return $signals;
    }

    /**
     * Compute signal strength (0.0–1.0) from the gap between score and threshold.
     */
    private function strengthFromGap(float $score): float
    {
        $gap = max(0.0, self::SIGNAL_THRESHOLD - $score);

        return min(self::MAX_STRENGTH, round($gap / self::SIGNAL_THRESHOLD, 2));
    }
}
