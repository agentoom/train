<?php

namespace App\Services\DatasetEvaluation\Evaluators;

use App\DTOs\DatasetBatchDTO;
use App\DTOs\EvaluatorResultDTO;
use App\Services\DatasetEvaluation\Contracts\DatasetEvaluatorInterface;

/**
 * Evaluates the quality of conversational rows.
 * Only applicable when conversation_enabled = true on the dataset project.
 * Checks turn counts, message structure, and refinement rates.
 */
class ConversationQualityEvaluator implements DatasetEvaluatorInterface
{
    /** Relative weight in the weighted average. */
    public const WEIGHT = 1;

    public function evaluate(DatasetBatchDTO $batch): EvaluatorResultDTO
    {
        $conversationEnabled = (bool) ($batch->metadata['conversation_enabled']
            ?? $batch->version->datasetProject?->conversation_enabled
            ?? false);

        if (! $conversationEnabled) {
            return new EvaluatorResultDTO(
                evaluator: 'conversation_quality',
                score: 0.0,
                passed: true,
                summary: 'Not applicable: conversation_enabled = false.',
                details: [],
                status: 'not_applicable',
                notApplicableReason: 'conversation_enabled = false',
                weight: self::WEIGHT,
            );
        }

        $rows = $batch->rows;
        $total = $rows->count();

        if ($total === 0) {
            return new EvaluatorResultDTO(
                evaluator: 'conversation_quality',
                score: 0.0,
                passed: false,
                summary: 'No rows to evaluate.',
                details: ['total_rows' => 0],
                weight: self::WEIGHT,
            );
        }

        $conversationalRows = $rows->filter(fn (array $row) => isset($row['messages']) && is_array($row['messages']));
        $conversationalCount = $conversationalRows->count();

        if ($conversationalCount === 0) {
            return new EvaluatorResultDTO(
                evaluator: 'conversation_quality',
                score: 75.0,
                passed: true,
                summary: 'No conversational rows detected; skipping conversation quality checks.',
                details: ['total_rows' => $total, 'conversational_rows' => 0],
                weight: self::WEIGHT,
            );
        }

        $minTurns = (int) ($batch->metadata['min_turns'] ?? $batch->version->datasetProject?->min_turns ?? 1);
        $maxTurns = (int) ($batch->metadata['max_turns'] ?? $batch->version->datasetProject?->max_turns ?? 20);

        $turnCounts = $conversationalRows->map(fn (array $row) => (int) ($row['turn_count'] ?? count($row['messages'] ?? [])));
        $avgTurns = $turnCounts->average();

        $refinedRows = $rows->filter(fn (array $row) => ! empty($row['refined_by']))->count();
        $refinementRate = $refinedRows / $total;

        $wellStructuredRows = $conversationalRows->filter(function (array $row): bool {
            $messages = $row['messages'] ?? [];

            return count($messages) >= 2 && isset($messages[0]['role']) && isset($messages[0]['content']);
        })->count();

        $structureRate = $wellStructuredRows / $conversationalCount;

        // Turns within configured range earn full bonus
        $midTurns = ($minTurns + $maxTurns) / 2.0;
        $turnBonus = min(20.0, ($avgTurns / max(1, $midTurns)) * 20.0);

        $score = $structureRate * 60.0;
        $score += $turnBonus;
        $score += (1.0 - min(1.0, $refinementRate * 2)) * 20.0;

        $score = round(min(100.0, max(0.0, $score)), 2);
        $passed = $score >= 60.0;

        return new EvaluatorResultDTO(
            evaluator: 'conversation_quality',
            score: $score,
            passed: $passed,
            summary: sprintf(
                'Conversational rows: %d/%d, avg turns: %.1f, well-structured: %.0f%%, refinement rate: %.0f%%.',
                $conversationalCount,
                $total,
                $avgTurns,
                $structureRate * 100,
                $refinementRate * 100,
            ),
            details: [
                'total_rows' => $total,
                'conversational_rows' => $conversationalCount,
                'avg_turns' => round($avgTurns, 2),
                'well_structured_rows' => $wellStructuredRows,
                'structure_rate' => round($structureRate, 4),
                'refined_rows' => $refinedRows,
                'refinement_rate' => round($refinementRate, 4),
            ],
            weight: self::WEIGHT,
        );
    }
}
