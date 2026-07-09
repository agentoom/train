<?php

namespace App\Services\DatasetEvaluation;

use App\DTOs\BatchEvaluationResultDTO;
use App\DTOs\DatasetBatchDTO;
use App\DTOs\EvaluatorResultDTO;
use App\Services\DatasetEvaluation\Contracts\DatasetEvaluatorInterface;

/**
 * Runs a sequence of dataset evaluators and aggregates their results
 * into a single BatchEvaluationResultDTO.
 *
 * Scoring rules:
 * - Only applicable evaluators contribute to the overall score.
 * - Scores are weighted by each evaluator's declared weight.
 * - Critical evaluators (task_performance, distribution_drift) can hard-fail the dataset.
 * - Non-critical evaluators never hard-fail on their own.
 *
 * Pass criteria:
 * - overall_score >= 60
 * - no critical evaluator score < 35
 *
 * Quality bands:
 * - 90–100 → Excellent
 * - 75–89  → Strong
 * - 60–74  → Good
 * - 40–59  → Weak
 * - 0–39   → Poor
 */
class EvaluationPipeline
{
    /** Evaluators whose low score can hard-fail the dataset. */
    private const CRITICAL_EVALUATORS = ['task_performance', 'distribution_drift'];

    /** Minimum overall score to pass. */
    private const PASS_THRESHOLD = 60.0;

    /** Minimum score a critical evaluator may reach before hard-failing. */
    private const CRITICAL_FLOOR = 35.0;

    /** @var array<DatasetEvaluatorInterface> */
    private array $evaluators;

    /** @param  array<DatasetEvaluatorInterface>  $evaluators */
    public function __construct(array $evaluators)
    {
        $this->evaluators = $evaluators;
    }

    public function run(DatasetBatchDTO $batch): BatchEvaluationResultDTO
    {
        $results = [];

        foreach ($this->evaluators as $evaluator) {
            $result = $evaluator->evaluate($batch);
            $results[$result->evaluator] = $result;
        }

        $applicableResults = array_filter($results, fn (EvaluatorResultDTO $r) => $r->isApplicable());
        $skippedResults = array_filter($results, fn (EvaluatorResultDTO $r) => ! $r->isApplicable());

        $overallScore = $this->aggregateScore($applicableResults);
        $passed = $this->determinePassed($applicableResults, $overallScore);
        $qualityBand = $this->resolveQualityBand($overallScore);
        $verdict = $this->buildVerdict($applicableResults, $skippedResults, $passed, $qualityBand, $overallScore);

        return new BatchEvaluationResultDTO(
            overallScore: $overallScore,
            passed: $passed,
            verdict: $verdict,
            evaluatorResults: $results,
            metadata: [
                'total_evaluators' => count($results),
                'applicable_evaluators' => count($applicableResults),
                'skipped_evaluators' => array_map(
                    fn (EvaluatorResultDTO $r) => $r->notApplicableReason ?? 'not_applicable',
                    $skippedResults,
                ),
                'passed_evaluators' => count(array_filter($applicableResults, fn (EvaluatorResultDTO $r) => $r->passed)),
                'failed_evaluators' => array_keys(array_filter($applicableResults, fn (EvaluatorResultDTO $r) => ! $r->passed)),
                'quality_band' => $qualityBand,
            ],
        );
    }

    /**
     * Compute a weighted average score across applicable evaluator results only.
     *
     * @param  array<string, EvaluatorResultDTO>  $results
     */
    private function aggregateScore(array $results): float
    {
        if (empty($results)) {
            return 0.0;
        }

        $weightedSum = 0.0;
        $totalWeight = 0;

        foreach ($results as $result) {
            $weightedSum += $result->score * $result->weight;
            $totalWeight += $result->weight;
        }

        if ($totalWeight === 0) {
            return 0.0;
        }

        return round($weightedSum / $totalWeight, 2);
    }

    /**
     * A batch passes if:
     * - overall score >= 60
     * - no critical evaluator scores below 35
     *
     * Non-critical evaluators never hard-fail the dataset.
     *
     * @param  array<string, EvaluatorResultDTO>  $results  Applicable results only
     */
    private function determinePassed(array $results, float $overallScore): bool
    {
        if ($overallScore < self::PASS_THRESHOLD) {
            return false;
        }

        foreach ($results as $name => $result) {
            if (in_array($name, self::CRITICAL_EVALUATORS, true) && $result->score < self::CRITICAL_FLOOR) {
                return false;
            }
        }

        return true;
    }

    /**
     * Map an overall score to a quality band label.
     */
    private function resolveQualityBand(float $score): string
    {
        return match (true) {
            $score >= 90.0 => 'Excellent',
            $score >= 75.0 => 'Strong',
            $score >= 60.0 => 'Good',
            $score >= 40.0 => 'Weak',
            default => 'Poor',
        };
    }

    /**
     * Build a human-readable verdict string with explainability details.
     *
     * @param  array<string, EvaluatorResultDTO>  $applicableResults
     * @param  array<string, EvaluatorResultDTO>  $skippedResults
     */
    private function buildVerdict(
        array $applicableResults,
        array $skippedResults,
        bool $passed,
        string $qualityBand,
        float $overallScore,
    ): string {
        $parts = [sprintf('Quality band: %s (score: %.1f).', $qualityBand, $overallScore)];

        if (! empty($skippedResults)) {
            $skipped = array_map(
                fn (string $name, EvaluatorResultDTO $r) => sprintf('%s (%s)', $name, $r->notApplicableReason ?? 'not_applicable'),
                array_keys($skippedResults),
                $skippedResults,
            );
            $parts[] = sprintf('Skipped evaluators: %s.', implode(', ', $skipped));
        }

        if ($passed) {
            $parts[] = 'Dataset passed all evaluation criteria and is approved for release.';

            return implode(' ', $parts);
        }

        $criticalFailures = array_keys(array_filter(
            $applicableResults,
            fn (EvaluatorResultDTO $r, string $name) => in_array($name, self::CRITICAL_EVALUATORS, true) && $r->score < self::CRITICAL_FLOOR,
            ARRAY_FILTER_USE_BOTH,
        ));

        if (! empty($criticalFailures)) {
            $parts[] = sprintf('Dataset rejected: critical evaluators below floor — %s.', implode(', ', $criticalFailures));
        } else {
            $parts[] = 'Dataset rejected: overall score below threshold.';
        }

        $improvable = array_keys(array_filter($applicableResults, fn (EvaluatorResultDTO $r) => ! $r->passed));
        if (! empty($improvable)) {
            $parts[] = sprintf('Improvement suggested for: %s.', implode(', ', $improvable));
        }

        return implode(' ', $parts);
    }
}
