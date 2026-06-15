<?php

namespace App\Services\DatasetEvaluation\Evaluators;

use App\DTOs\DatasetBatchDTO;
use App\DTOs\EvaluatorResultDTO;
use App\Services\DatasetEvaluation\Contracts\DatasetEvaluatorInterface;

/**
 * Evaluates how well dataset rows demonstrate correct task performance.
 * Checks quality scores, evaluation failures, and validity rates.
 */
class TaskPerformanceEvaluator implements DatasetEvaluatorInterface
{
    /** Relative weight in the weighted average. */
    public const WEIGHT = 3;

    public function evaluate(DatasetBatchDTO $batch): EvaluatorResultDTO
    {
        $rows = $batch->rows;
        $total = $rows->count();

        if ($total === 0) {
            return new EvaluatorResultDTO(
                evaluator: 'task_performance',
                score: 0.0,
                passed: false,
                summary: 'No rows to evaluate.',
                details: ['total_rows' => 0],
                weight: self::WEIGHT,
            );
        }

        $validRows = $rows->filter(fn (array $row) => ($row['is_valid'] ?? true) === true)->count();
        $evaluationFailures = $rows->filter(fn (array $row) => ($row['evaluation_failed'] ?? false) === true)->count();
        $qualityScores = $rows->pluck('quality_score')->filter(fn ($s) => $s !== null)->map(fn ($s) => (float) $s);

        $validityRate = $validRows / $total;
        $failureRate = $evaluationFailures / $total;
        $avgQuality = $qualityScores->isNotEmpty() ? $qualityScores->average() : null;

        $score = $validityRate * 60.0;
        $score += (1.0 - $failureRate) * 20.0;

        if ($avgQuality !== null) {
            $score += ($avgQuality / 100.0) * 20.0;
        } else {
            $score += 10.0;
        }

        $score = round(min(100.0, max(0.0, $score)), 2);
        $passed = $score >= 60.0;

        return new EvaluatorResultDTO(
            evaluator: 'task_performance',
            score: $score,
            passed: $passed,
            summary: sprintf(
                'Validity rate: %.0f%%, evaluation failure rate: %.0f%%, avg quality score: %s.',
                $validityRate * 100,
                $failureRate * 100,
                $avgQuality !== null ? round($avgQuality, 1) : 'n/a',
            ),
            details: [
                'total_rows' => $total,
                'valid_rows' => $validRows,
                'validity_rate' => round($validityRate, 4),
                'evaluation_failures' => $evaluationFailures,
                'failure_rate' => round($failureRate, 4),
                'avg_quality_score' => $avgQuality !== null ? round($avgQuality, 2) : null,
            ],
            weight: self::WEIGHT,
        );
    }
}
