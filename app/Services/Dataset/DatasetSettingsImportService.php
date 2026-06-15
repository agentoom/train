<?php

namespace App\Services\Dataset;

use App\Models\DatasetProject;
use App\Models\User;
use Illuminate\Support\Arr;

/**
 * Imports dataset project settings from a JSON export produced by DatasetSettingsExportService.
 *
 * Creates new DatasetProject records — never overwrites existing ones.
 * Provider IDs are not imported (they are environment-specific).
 */
class DatasetSettingsImportService
{
    /** @var list<string> */
    private const ALLOWED_FIELDS = [
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
     * Parse and validate a JSON string, returning the list of dataset payloads.
     *
     * @return list<array<string, mixed>>
     *
     * @throws \InvalidArgumentException
     */
    public function parse(string $json): array
    {
        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: '.json_last_error_msg());
        }

        // Support both single-dataset and multi-dataset export formats.
        if (isset($decoded['datasets']) && is_array($decoded['datasets'])) {
            $datasets = $decoded['datasets'];
        } elseif (isset($decoded['name'])) {
            $datasets = [$decoded];
        } else {
            throw new \InvalidArgumentException('Unrecognised export format. Expected a datasets array or a single dataset object.');
        }

        if (empty($datasets)) {
            throw new \InvalidArgumentException('The export file contains no datasets.');
        }

        foreach ($datasets as $index => $dataset) {
            if (empty($dataset['name'])) {
                throw new \InvalidArgumentException("Dataset at index {$index} is missing a required \"name\" field.");
            }
        }

        return $datasets;
    }

    /**
     * Import all datasets from a parsed payload, creating new projects for the given user.
     *
     * @param  list<array<string, mixed>>  $datasets
     * @return list<DatasetProject>
     */
    public function import(array $datasets, User $user): array
    {
        $created = [];

        foreach ($datasets as $data) {
            $fields = Arr::only($data, self::ALLOWED_FIELDS);
            $fields['user_id'] = $user->id;

            // The DB stores negative_example_ratio as an integer percentage (e.g. 20 = 20%).
            // JSON exports from this app already store the integer, but manually authored
            // config files may use a float ratio (e.g. 0.2). Normalise both forms.
            if (isset($fields['negative_example_ratio'])) {
                $ratio = $fields['negative_example_ratio'];
                $fields['negative_example_ratio'] = ($ratio > 0 && $ratio < 1)
                    ? (int) round($ratio * 100)
                    : (int) $ratio;
            }

            $created[] = DatasetProject::create($fields);
        }

        return $created;
    }
}
