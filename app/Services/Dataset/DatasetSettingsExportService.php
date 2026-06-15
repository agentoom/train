<?php

namespace App\Services\Dataset;

use App\Models\DatasetProject;
use Illuminate\Support\Collection;

/**
 * Exports dataset project settings as a portable JSON structure.
 *
 * Only configuration fields are exported — no rows, no user-specific IDs,
 * no provider IDs (which are environment-specific).
 */
class DatasetSettingsExportService
{
    /** @var list<string> */
    private const SETTINGS_FIELDS = [
        'name',
        'description',
        'system_prompt',
        'schema',
        'record_count',
        'chunk_size',
        'model',
        'temperature',
        'max_tokens',
        'strategy',
        'diversity_dimensions',
        'generation_seed',
        'uniqueness_level',
        'semantic_deduplication_enabled',
        'semantic_similarity_threshold',
        'replacement_generation_enabled',
        'evaluation_enabled',
        'evaluation_model',
        'minimum_quality_score',
        'critic_enabled',
        'critic_model',
        'refiner_enabled',
        'refiner_model',
        'negative_example_ratio',
        'conversation_enabled',
        'min_turns',
        'max_turns',
        'branching_enabled',
        'conversation_type',
        'augmentation_enabled',
        'augmentation_mode',
        'augmentation_strength',
        'augmentation_target_count',
        'augmentation_expand_percent',
    ];

    /**
     * Export a single project's settings as an array.
     *
     * @return array<string, mixed>
     */
    public function exportOne(DatasetProject $project): array
    {
        return $this->buildPayload($project);
    }

    /**
     * Export multiple projects' settings as a JSON string.
     *
     * @param  Collection<int, DatasetProject>  $projects
     */
    public function exportMany(Collection $projects): string
    {
        $payload = [
            'version' => '1.0',
            'exported_at' => now()->toIso8601String(),
            'datasets' => $projects->map(fn (DatasetProject $p) => $this->buildPayload($p))->values()->all(),
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(DatasetProject $project): array
    {
        $settings = [];

        foreach (self::SETTINGS_FIELDS as $field) {
            $settings[$field] = $project->getAttribute($field);
        }

        return $settings;
    }
}
