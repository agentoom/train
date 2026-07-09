<?php

namespace App\Services\DatasetEvaluation\Evaluators;

use App\DTOs\DatasetBatchDTO;
use App\DTOs\EvaluatorResultDTO;
use App\Services\DatasetEvaluation\Contracts\DatasetEvaluatorInterface;

/**
 * Evaluates distribution drift by comparing the current batch against
 * the baseline version stored in batch metadata.
 * Checks duplicate rates and payload field coverage consistency.
 */
class DistributionDriftEvaluator implements DatasetEvaluatorInterface
{
    /** Relative weight in the weighted average. */
    public const WEIGHT = 2;

    public function evaluate(DatasetBatchDTO $batch): EvaluatorResultDTO
    {
        $rows = $batch->rows;
        $total = $rows->count();

        if ($total === 0) {
            return new EvaluatorResultDTO(
                evaluator: 'distribution_drift',
                score: 0.0,
                passed: false,
                summary: 'No rows to evaluate.',
                details: ['total_rows' => 0],
                weight: self::WEIGHT,
            );
        }

        $duplicateCount = $rows->filter(fn (array $row) => ($row['is_duplicate'] ?? false) === true)->count();
        $duplicateRate = $duplicateCount / $total;

        $fieldCoverages = $this->computeFieldCoverages($rows->all());
        $lowCoverageFields = array_filter($fieldCoverages, fn (float $coverage) => $coverage < 0.8);

        $baselineDuplicateRate = (float) ($batch->metadata['baseline_duplicate_rate'] ?? 0.0);
        $duplicateDrift = abs($duplicateRate - $baselineDuplicateRate);

        // Calibrated duplicate penalty curve:
        // 0–3%  → 0 penalty, 3–7% → up to 15, 7–15% → up to 35, 15–25% → up to 55, 25%+ → up to 80
        $duplicatePenalty = $this->computeDuplicatePenalty($duplicateRate);

        // Drift penalty capped at 20 (was 40 with 200x multiplier — too harsh)
        $driftPenalty = min(20.0, $duplicateDrift * 100.0);

        $coveragePenalty = min(20.0, count($lowCoverageFields) * 5.0);

        $score = 100.0 - $duplicatePenalty - $driftPenalty - $coveragePenalty;
        $score = round(min(100.0, max(0.0, $score)), 2);
        $passed = $score >= 55.0;

        return new EvaluatorResultDTO(
            evaluator: 'distribution_drift',
            score: $score,
            passed: $passed,
            summary: sprintf(
                'Duplicate rate: %.1f%% (baseline: %.1f%%), drift: %.1f%%, low-coverage fields: %d.',
                $duplicateRate * 100,
                $baselineDuplicateRate * 100,
                $duplicateDrift * 100,
                count($lowCoverageFields),
            ),
            details: [
                'total_rows' => $total,
                'duplicate_count' => $duplicateCount,
                'duplicate_rate' => round($duplicateRate, 4),
                'baseline_duplicate_rate' => round($baselineDuplicateRate, 4),
                'duplicate_drift' => round($duplicateDrift, 4),
                'field_coverages' => array_map(fn (float $c) => round($c, 4), $fieldCoverages),
                'low_coverage_fields' => array_keys($lowCoverageFields),
            ],
            weight: self::WEIGHT,
        );
    }

    /**
     * Calibrated duplicate penalty curve.
     * 0–3%   → 95–100 (penalty 0–5)
     * 3–7%   → 80–95  (penalty 5–20)
     * 7–15%  → 60–80  (penalty 20–40)
     * 15–25% → 40–60  (penalty 40–60)
     * 25%+   → 0–40   (penalty 60–100)
     */
    private function computeDuplicatePenalty(float $rate): float
    {
        return match (true) {
            $rate <= 0.03 => $rate / 0.03 * 5.0,
            $rate <= 0.07 => 5.0 + (($rate - 0.03) / 0.04) * 15.0,
            $rate <= 0.15 => 20.0 + (($rate - 0.07) / 0.08) * 20.0,
            $rate <= 0.25 => 40.0 + (($rate - 0.15) / 0.10) * 20.0,
            default => 60.0 + min(40.0, (($rate - 0.25) / 0.75) * 40.0),
        };
    }

    /**
     * Compute the fraction of rows that contain each payload field.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, float>
     */
    private function computeFieldCoverages(array $rows): array
    {
        $total = count($rows);

        if ($total === 0) {
            return [];
        }

        $fieldCounts = [];

        foreach ($rows as $row) {
            $payload = $row['payload'] ?? $row;

            if (! is_array($payload)) {
                continue;
            }

            foreach (array_keys($payload) as $field) {
                $fieldCounts[$field] = ($fieldCounts[$field] ?? 0) + 1;
            }
        }

        return array_map(fn (int $count) => $count / $total, $fieldCounts);
    }
}
