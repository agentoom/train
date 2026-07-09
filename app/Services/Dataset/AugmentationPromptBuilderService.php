<?php

namespace App\Services\Dataset;

use App\DTOs\PromptConfigDTO;
use App\Models\DatasetProject;
use App\Models\DatasetVersion;

class AugmentationPromptBuilderService
{
    /** Minimum and maximum examples to include per batch prompt. */
    private const MIN_EXAMPLES = 5;

    private const MAX_EXAMPLES = 20;

    /**
     * Build a prompt for augmentation generation from a project.
     *
     * @param  array<int, array<string, mixed>>  $sourceRows  Sampled rows from the uploaded source dataset.
     */
    public function build(
        DatasetProject $project,
        array $sourceRows,
        int $batchIndex = 0,
        ?int $recordCount = null,
        int $totalBatches = 1,
    ): PromptConfigDTO {
        $count = $recordCount ?? $project->record_count ?? 10;
        $mode = $project->augmentation_mode ?? 'similar';
        $strength = (float) ($project->augmentation_strength ?? 0.5);

        $userPrompt = $this->buildUserPrompt(
            sourceRows: $sourceRows,
            mode: $mode,
            strength: $strength,
            schema: $project->schema,
            recordCount: $count,
            batchIndex: $batchIndex,
            totalBatches: $totalBatches,
        );

        return new PromptConfigDTO(
            systemPrompt: $this->buildSystemPrompt($mode),
            userPrompt: $userPrompt,
            model: $project->model ?? 'gpt-4o-mini',
            temperature: $this->temperatureForStrength($strength),
            maxTokens: $project->max_tokens ?? 2048,
            schema: $project->schema,
            jsonMode: $project->schema !== null,
        );
    }

    /**
     * Build a prompt for augmentation generation from a version snapshot.
     *
     * @param  array<int, array<string, mixed>>  $sourceRows
     */
    public function buildFromVersion(
        DatasetVersion $version,
        array $sourceRows,
        int $batchIndex = 0,
        ?int $recordCount = null,
        int $totalBatches = 1,
    ): PromptConfigDTO {
        $project = $version->datasetProject;
        $count = $recordCount ?? $version->record_count ?? 10;
        $mode = $project?->augmentation_mode ?? 'similar';
        $strength = (float) ($project?->augmentation_strength ?? 0.5);

        $userPrompt = $this->buildUserPrompt(
            sourceRows: $sourceRows,
            mode: $mode,
            strength: $strength,
            schema: $version->schema_snapshot,
            recordCount: $count,
            batchIndex: $batchIndex,
            totalBatches: $totalBatches,
        );

        return new PromptConfigDTO(
            systemPrompt: $this->buildSystemPrompt($mode),
            userPrompt: $userPrompt,
            model: $version->model ?? 'gpt-4o-mini',
            temperature: $this->temperatureForStrength($strength),
            maxTokens: $project?->max_tokens ?? 2048,
            schema: $version->schema_snapshot,
            jsonMode: $version->schema_snapshot !== null,
        );
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function buildSystemPrompt(string $mode): string
    {
        $modeInstructions = match ($mode) {
            'diverse' => 'Generate broader scenarios inspired by the examples. Increase novelty while staying realistic and domain-consistent.',
            'edge_cases' => 'Generate difficult, unusual, or edge-case scenarios based on the examples. Include angry customers, incomplete information, conflicting requests, ambiguous intent, and rare workflows.',
            'adversarial' => 'Generate adversarial or failure scenarios based on the examples. Include malicious prompts, tool confusion, contradictory instructions, and unrealistic user behavior.',
            default => 'Generate realistic variants that are close to the original examples. Preserve domain language, tone, and structure.',
        };

        return implode("\n", [
            'You are generating synthetic training data based on real business examples.',
            '',
            'Core rules:',
            '- Preserve realism and domain consistency.',
            '- Do NOT copy examples directly.',
            '- Avoid near-verbatim reproduction or memorization.',
            '- Generate novel but believable variations.',
            '- Maintain the vocabulary, tone, and entity types of the source domain.',
            '',
            $modeInstructions,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sourceRows
     * @param  array<string, mixed>|null  $schema
     */
    private function buildUserPrompt(
        array $sourceRows,
        string $mode,
        float $strength,
        ?array $schema,
        int $recordCount,
        int $batchIndex,
        int $totalBatches,
    ): string {
        $parts = [];

        // Record count
        $entry = $recordCount === 1 ? 'entry' : 'entries';
        $parts[] = "Generate EXACTLY {$recordCount} dataset {$entry}.

OUTPUT FORMAT REQUIREMENTS:
- Return ONLY valid JSON.
- The root JSON element MUST be an ARRAY.
- The array MUST contain EXACTLY {$recordCount} objects.
- Do NOT copy source examples directly.
- Do NOT return a single object.
- Do NOT wrap JSON in markdown.
- Output must be parseable by JSON.parse().";

        // Batch awareness
        $batchNum = $batchIndex + 1;
        $parts[] = "This is batch {$batchNum} of {$totalBatches}. Each batch must be fully independent and unique.";

        // Schema
        if ($schema) {
            $parts[] = "Each entry MUST match this JSON schema exactly:\n".json_encode($schema, JSON_PRETTY_PRINT);
        }

        // Mode-specific instructions
        $parts[] = $this->modeInstructions($mode, $strength);

        // Source examples
        $exampleCount = $this->exampleCount($sourceRows, $strength);
        $examples = $this->selectExamples($sourceRows, $exampleCount, $batchIndex);

        if (! empty($examples)) {
            $exampleJson = json_encode($examples, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $parts[] = "SOURCE EXAMPLES (do NOT copy — use as inspiration only):\n{$exampleJson}";
        }

        // Leakage prevention
        $parts[] = implode("\n", [
            'LEAKAGE PREVENTION:',
            '- Do NOT reproduce any source example verbatim.',
            '- Do NOT produce near-verbatim copies (paraphrasing is not enough — change the scenario).',
            '- Every generated entry must be clearly distinct from all source examples.',
            '- Vary names, numbers, dates, entities, and scenarios.',
        ]);

        return implode("\n\n", $parts);
    }

    private function modeInstructions(string $mode, float $strength): string
    {
        $strengthLabel = match (true) {
            $strength <= 0.3 => 'low (stay very close to source patterns)',
            $strength >= 0.7 => 'high (strong novelty, significant variation)',
            default => 'medium (balanced variation)',
        };

        $base = "Augmentation strength: {$strengthLabel}.";

        return $base."\n".match ($mode) {
            'diverse' => 'Mode: DIVERSE — Generate broader scenarios inspired by originals. Increase novelty. Cover different sub-topics, use cases, and user types.',
            'edge_cases' => 'Mode: EDGE CASES — Generate difficult scenarios: angry users, incomplete information, conflicting requests, ambiguous intent, rare workflows.',
            'adversarial' => 'Mode: ADVERSARIAL — Generate failure scenarios: malicious prompts, tool confusion, contradictory instructions, jailbreak attempts, unrealistic behavior.',
            default => 'Mode: SIMILAR — Generate realistic variants close to originals. Preserve domain language and structure while varying specific details.',
        };
    }

    /**
     * Determine how many source examples to include based on strength.
     *
     * @param  array<int, array<string, mixed>>  $sourceRows
     */
    private function exampleCount(array $sourceRows, float $strength): int
    {
        if (empty($sourceRows)) {
            return 0;
        }

        // Higher strength → fewer examples (more freedom), lower → more examples (more grounding)
        $ratio = 1.0 - $strength;
        $count = (int) round(self::MIN_EXAMPLES + $ratio * (self::MAX_EXAMPLES - self::MIN_EXAMPLES));

        return min($count, count($sourceRows));
    }

    /**
     * Select examples from source rows, rotating by batch index.
     *
     * @param  array<int, array<string, mixed>>  $sourceRows
     * @return array<int, array<string, mixed>>
     */
    private function selectExamples(array $sourceRows, int $count, int $batchIndex): array
    {
        if (empty($sourceRows) || $count <= 0) {
            return [];
        }

        $total = count($sourceRows);
        $offset = ($batchIndex * $count) % $total;

        return array_values(array_slice(array_merge($sourceRows, $sourceRows), $offset, $count));
    }

    /**
     * Map augmentation strength (0.1–1.0) to LLM temperature.
     * Low strength → lower temperature (more conservative).
     * High strength → higher temperature (more creative).
     */
    private function temperatureForStrength(float $strength): float
    {
        // Map 0.1–1.0 → 0.4–1.0
        return round(0.4 + ($strength * 0.6), 2);
    }
}
