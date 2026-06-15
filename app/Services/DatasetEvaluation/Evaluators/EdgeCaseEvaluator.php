<?php

namespace App\Services\DatasetEvaluation\Evaluators;

use App\DTOs\DatasetBatchDTO;
use App\DTOs\EvaluatorResultDTO;
use App\Services\DatasetEvaluation\Contracts\DatasetEvaluatorInterface;

/**
 * Evaluates the presence and quality of edge cases in the dataset.
 * Checks for rows with low similarity scores, critic feedback, and
 * source similarity diversity.
 *
 * Not applicable for deterministic classification or simple extraction datasets
 * that have no similarity scoring data at all.
 */
class EdgeCaseEvaluator implements DatasetEvaluatorInterface
{
    /** Relative weight in the weighted average. */
    public const WEIGHT = 2;

    /**
     * Dataset types that are exempt from edge-case requirements.
     * These types naturally lack diversity signals and should not be penalised.
     */
    private const EXEMPT_TYPES = ['classification', 'extraction', 'structured_output'];

    public function evaluate(DatasetBatchDTO $batch): EvaluatorResultDTO
    {
        $datasetType = (string) ($batch->metadata['dataset_type']
            ?? $batch->version->datasetProject?->dataset_type
            ?? '');

        if (in_array($datasetType, self::EXEMPT_TYPES, true)) {
            return new EvaluatorResultDTO(
                evaluator: 'edge_case',
                score: 0.0,
                passed: true,
                summary: sprintf('Not applicable: dataset_type = %s does not require edge-case diversity.', $datasetType),
                details: ['dataset_type' => $datasetType],
                status: 'not_applicable',
                notApplicableReason: sprintf('dataset_type = %s', $datasetType),
                weight: self::WEIGHT,
            );
        }

        $rows = $batch->rows;
        $total = $rows->count();

        if ($total === 0) {
            return new EvaluatorResultDTO(
                evaluator: 'edge_case',
                score: 0.0,
                passed: false,
                summary: 'No rows to evaluate.',
                details: ['total_rows' => 0],
                weight: self::WEIGHT,
            );
        }

        $rowsWithCriticFeedback = $rows->filter(fn (array $row) => ! empty($row['critic_feedback']))->count();
        $rowsWithSimilarityScore = $rows->filter(fn (array $row) => isset($row['source_similarity_score']))->count();

        $similarityScores = $rows
            ->pluck('source_similarity_score')
            ->filter(fn ($s) => $s !== null)
            ->map(fn ($s) => (float) $s);

        $avgSimilarity = $similarityScores->isNotEmpty() ? $similarityScores->average() : null;

        $lowSimilarityRows = $similarityScores->filter(fn (float $s) => $s < 0.5)->count();
        $edgeCaseRate = $rowsWithSimilarityScore > 0
            ? $lowSimilarityRows / $rowsWithSimilarityScore
            : 0.0;

        $criticCoverageRate = $rowsWithCriticFeedback / $total;

        // When no similarity data exists, give a neutral baseline (not a penalty)
        if ($avgSimilarity === null && $rowsWithSimilarityScore === 0) {
            $score = 70.0;
            $score += min(10.0, $criticCoverageRate * 20.0);
            $score = round(min(100.0, max(0.0, $score)), 2);
            $passed = $score >= 55.0;

            return new EvaluatorResultDTO(
                evaluator: 'edge_case',
                score: $score,
                passed: $passed,
                summary: sprintf(
                    'No similarity data available; critic feedback coverage: %.1f%%.',
                    $criticCoverageRate * 100,
                ),
                details: [
                    'total_rows' => $total,
                    'rows_with_critic_feedback' => $rowsWithCriticFeedback,
                    'critic_coverage_rate' => round($criticCoverageRate, 4),
                    'rows_with_similarity_score' => 0,
                    'low_similarity_rows' => 0,
                    'edge_case_rate' => 0.0,
                    'avg_source_similarity' => null,
                ],
                weight: self::WEIGHT,
            );
        }

        $score = 60.0;
        $diversityBonus = (1.0 - ($avgSimilarity ?? 0.5)) * 25.0;
        $score += $diversityBonus;
        $score += min(15.0, $edgeCaseRate * 30.0);
        $score += min(10.0, $criticCoverageRate * 20.0);

        $score = round(min(100.0, max(0.0, $score)), 2);
        $passed = $score >= 55.0;

        return new EvaluatorResultDTO(
            evaluator: 'edge_case',
            score: $score,
            passed: $passed,
            summary: sprintf(
                'Edge case rate: %.1f%%, avg source similarity: %s, critic feedback coverage: %.1f%%.',
                $edgeCaseRate * 100,
                $avgSimilarity !== null ? round($avgSimilarity, 3) : 'n/a',
                $criticCoverageRate * 100,
            ),
            details: [
                'total_rows' => $total,
                'rows_with_critic_feedback' => $rowsWithCriticFeedback,
                'critic_coverage_rate' => round($criticCoverageRate, 4),
                'rows_with_similarity_score' => $rowsWithSimilarityScore,
                'low_similarity_rows' => $lowSimilarityRows,
                'edge_case_rate' => round($edgeCaseRate, 4),
                'avg_source_similarity' => $avgSimilarity !== null ? round($avgSimilarity, 4) : null,
            ],
            weight: self::WEIGHT,
        );
    }
}
