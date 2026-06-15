<?php

namespace App\Services\DatasetEvaluation\Evaluators;

use App\DTOs\DatasetBatchDTO;
use App\DTOs\EvaluatorResultDTO;
use App\Services\DatasetEvaluation\Contracts\DatasetEvaluatorInterface;

/**
 * Evaluates whether the ratio of negative examples in the dataset
 * falls within the expected configurable range.
 * Only applicable when negative_example_ratio > 0 on the dataset project.
 */
class NegativeRatioEvaluator implements DatasetEvaluatorInterface
{
    /** Relative weight in the weighted average. */
    public const WEIGHT = 1;

    public function __construct(
        private readonly float $minNegativeRatio = 0.05,
        private readonly float $maxNegativeRatio = 0.40,
    ) {}

    public function evaluate(DatasetBatchDTO $batch): EvaluatorResultDTO
    {
        // Resolve the configured negative example ratio from project or metadata
        $configuredRatio = (float) ($batch->metadata['negative_example_ratio']
            ?? $batch->version->datasetProject?->negative_example_ratio
            ?? 0);

        if ($configuredRatio <= 0) {
            return new EvaluatorResultDTO(
                evaluator: 'negative_ratio',
                score: 0.0,
                passed: true,
                summary: 'Not applicable: negative_example_ratio = 0.',
                details: [],
                status: 'not_applicable',
                notApplicableReason: 'negative_example_ratio = 0',
                weight: self::WEIGHT,
            );
        }

        $rows = $batch->rows;
        $total = $rows->count();

        if ($total === 0) {
            return new EvaluatorResultDTO(
                evaluator: 'negative_ratio',
                score: 0.0,
                passed: false,
                summary: 'No rows to evaluate.',
                details: ['total_rows' => 0],
                weight: self::WEIGHT,
            );
        }

        $minRatio = (float) ($batch->metadata['min_negative_ratio'] ?? $this->minNegativeRatio);
        $maxRatio = (float) ($batch->metadata['max_negative_ratio'] ?? $this->maxNegativeRatio);

        $negativeRows = $rows->filter(fn (array $row) => ! empty($row['failure_reason']) || ! empty($row['expected_behavior']))->count();
        $negativeRatio = $negativeRows / $total;

        $withinRange = $negativeRatio >= $minRatio && $negativeRatio <= $maxRatio;

        if ($withinRange) {
            $score = 100.0;
        } elseif ($negativeRatio < $minRatio) {
            $score = max(0.0, ($negativeRatio / $minRatio) * 70.0);
        } else {
            $overshoot = ($negativeRatio - $maxRatio) / (1.0 - $maxRatio);
            $score = max(0.0, 70.0 - ($overshoot * 70.0));
        }

        $score = round($score, 2);
        $passed = $score >= 60.0;

        return new EvaluatorResultDTO(
            evaluator: 'negative_ratio',
            score: $score,
            passed: $passed,
            summary: sprintf(
                'Negative ratio: %.1f%% (expected %.1f%%–%.1f%%). %s',
                $negativeRatio * 100,
                $minRatio * 100,
                $maxRatio * 100,
                $withinRange ? 'Within acceptable range.' : 'Outside acceptable range.',
            ),
            details: [
                'total_rows' => $total,
                'negative_rows' => $negativeRows,
                'negative_ratio' => round($negativeRatio, 4),
                'min_negative_ratio' => $minRatio,
                'max_negative_ratio' => $maxRatio,
                'within_range' => $withinRange,
            ],
            weight: self::WEIGHT,
        );
    }
}
