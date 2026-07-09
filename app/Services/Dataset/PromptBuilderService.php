<?php

namespace App\Services\Dataset;

use App\DTOs\PromptConfigDTO;
use App\Models\DatasetProject;
use App\Models\DatasetVersion;

class PromptBuilderService
{
    public function build(DatasetProject $project, int $batchIndex = 0, ?int $recordCount = null, int $totalBatches = 1): PromptConfigDTO
    {
        $userPrompt = $this->buildUserPrompt(
            schema: $project->schema,
            strategy: $project->strategy,
            batchIndex: $batchIndex,
            totalBatches: $totalBatches,
            recordCount: $recordCount ?? $project->record_count,
            diversityDimensions: $project->diversity_dimensions,
            uniquenessLevel: $project->uniqueness_level ?? 'balanced',
            generationSeed: $project->generation_seed,
        );

        return new PromptConfigDTO(
            systemPrompt: $project->system_prompt ?? 'You are a helpful assistant that generates structured dataset entries.',
            userPrompt: $userPrompt,
            model: $project->model ?? 'gpt-4o-mini',
            temperature: $project->temperature ?? 0.7,
            maxTokens: $project->max_tokens ?? 2048,
            schema: $project->schema,
            jsonMode: $project->schema !== null,
        );
    }

    public function buildFromVersion(DatasetVersion $version, int $batchIndex = 0, ?int $recordCount = null, int $totalBatches = 1): PromptConfigDTO
    {
        $schema = $version->schema_snapshot;
        $project = $version->datasetProject;

        $userPrompt = $this->buildUserPrompt(
            schema: $schema,
            strategy: $project?->strategy,
            batchIndex: $batchIndex,
            totalBatches: $totalBatches,
            recordCount: $recordCount ?? $version->record_count,
            diversityDimensions: $project?->diversity_dimensions,
            uniquenessLevel: $project?->uniqueness_level ?? 'balanced',
            generationSeed: $project?->generation_seed,
        );

        return new PromptConfigDTO(
            systemPrompt: $version->system_prompt_snapshot ?? 'You are a helpful assistant that generates structured dataset entries.',
            userPrompt: $userPrompt,
            model: $version->model ?? 'gpt-4o-mini',
            temperature: $project?->temperature ?? 0.7,
            maxTokens: $project?->max_tokens ?? 2048,
            schema: $schema,
            jsonMode: $schema !== null,
        );
    }

    /**
     * @param  array<string, mixed>|null  $schema
     * @param  array<string, mixed>|null  $diversityDimensions
     */
    private function buildUserPrompt(
        ?array $schema,
        ?string $strategy,
        int $batchIndex,
        int $totalBatches,
        ?int $recordCount = null,
        ?array $diversityDimensions = null,
        string $uniquenessLevel = 'balanced',
        ?string $generationSeed = null,
    ): string {
        $parts = [];

        // Record count requirement
        $count = ($recordCount !== null && $recordCount > 0) ? $recordCount : 1;
        $entry = $count === 1 ? 'entry' : 'entries';
        $parts[] = "Generate EXACTLY {$count} dataset {$entry}.

            OUTPUT FORMAT REQUIREMENTS:

            - Return ONLY valid JSON.
            - The root JSON element MUST be an ARRAY.
            - The array MUST contain EXACTLY {$count} objects.
            - Every object MUST follow the schema exactly.
            - Do NOT return a single object.
            - Do NOT stop early.
            - Do NOT truncate output.
            - Do NOT wrap JSON in markdown.
            - Do NOT include explanations.
            - Output must be parseable by JSON.parse().
        ";

        // Batch awareness
        $batchNum = $batchIndex + 1;
        $parts[] = "This is batch {$batchNum} of {$totalBatches}. Each batch must be fully independent and unique.";

        // Schema
        if ($schema) {
            $parts[] = "Each entry MUST match this JSON schema exactly:\n".json_encode($schema, JSON_PRETTY_PRINT);
        }

        // Anti-duplication and uniqueness instructions
        $parts[] = $this->buildUniquenessInstructions($uniquenessLevel);

        // Diversity dimensions
        if (! empty($diversityDimensions)) {
            $parts[] = $this->buildDiversityInstructions($diversityDimensions, $batchIndex, $totalBatches);
        }

        // Strategy
        if ($strategy && $strategy !== 'default') {
            $parts[] = 'Generation strategy: '.$strategy;
        }

        // Seed context for reproducibility
        if ($generationSeed !== null) {
            $derivedSeed = $this->deriveBatchSeed($generationSeed, $batchIndex);
            $parts[] = "Reproducibility seed context: {$derivedSeed}. Use this to maintain consistency if regenerating.";
        }

        return implode("\n\n", $parts);
    }

    private function buildUniquenessInstructions(string $uniquenessLevel): string
    {
        $base = implode("\n", [
            'UNIQUENESS REQUIREMENTS:',
            '- Avoid duplicates and near-duplicates across all entries in this batch and previous batches.',
            '- Avoid repeating the same wording, topics, scenarios, or patterns.',
            '- Maximize diversity across all generated entries.',
            '- Respect all enum values and schema constraints.',
        ]);

        $extra = match ($uniquenessLevel) {
            'conservative' => '- Uniqueness level: Conservative. Some similarity is acceptable. Prioritize speed and coherence.',
            'aggressive' => '- Uniqueness level: Aggressive. Apply strong duplicate prevention. Every entry must be clearly distinct in content, structure, and wording.',
            default => '- Uniqueness level: Balanced. Good diversity while maintaining realism and coherence.',
        };

        return $base."\n".$extra;
    }

    /**
     * @param  array<string, mixed>  $diversityDimensions
     */
    private function buildDiversityInstructions(array $diversityDimensions, int $batchIndex, int $totalBatches): string
    {
        $lines = ['DIVERSITY REQUIREMENTS:'];
        $lines[] = 'Ensure balanced diversity across the following dimensions:';

        foreach ($diversityDimensions as $dimension => $values) {
            if (is_array($values) && ! empty($values)) {
                // Rotate preferred values based on batch index to avoid overrepresentation
                $preferred = $this->getPreferredValuesForBatch($values, $batchIndex, $totalBatches);
                $valueList = implode(', ', array_map(fn ($v) => '"'.$v.'"', $preferred));
                $lines[] = "- {$dimension}: favor {$valueList} in this batch";
            } elseif (is_string($values)) {
                $lines[] = "- {$dimension}: {$values}";
            }
        }

        $lines[] = 'Avoid overrepresenting any single value. Batches should favor underrepresented combinations.';

        return implode("\n", $lines);
    }

    /**
     * Rotate dimension values across batches for balanced distribution.
     *
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    private function getPreferredValuesForBatch(array $values, int $batchIndex, int $totalBatches): array
    {
        if (empty($values) || $totalBatches <= 1) {
            return $values;
        }

        $count = count($values);
        $perBatch = max(1, (int) ceil($count / $totalBatches));
        $offset = ($batchIndex * $perBatch) % $count;

        return array_values(array_slice(array_merge($values, $values), $offset, $perBatch));
    }

    /**
     * Derive a stable per-batch seed from the project seed and batch index.
     */
    private function deriveBatchSeed(string $projectSeed, int $batchIndex): string
    {
        return substr(hash('sha256', $projectSeed.':batch:'.$batchIndex), 0, 16);
    }
}
