<?php

namespace App\Actions\Datasets;

use App\Models\DatasetProject;

class UpdateDatasetProjectAction
{
    /** @return array<string, mixed>|null */
    private function decodeDiversityDimensions(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_array($raw)) {
            return $raw;
        }

        $decoded = $raw;
        for ($i = 0; $i < 10; $i++) {
            if (is_array($decoded)) {
                return $decoded;
            }
            $next = json_decode($decoded, true);
            if (json_last_error() !== JSON_ERROR_NONE || $next === null) {
                break;
            }
            $decoded = $next;
        }

        return is_array($decoded) ? $decoded : null;
    }

    public function execute(DatasetProject $project, array $data): DatasetProject
    {
        $schemaRaw = $data['schema'] ?? null;
        $schema = null;

        if ($schemaRaw && is_string($schemaRaw)) {
            $decoded = json_decode($schemaRaw, true);
            $schema = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        } elseif (is_array($schemaRaw)) {
            $schema = $schemaRaw;
        }

        $diversityDimensions = array_key_exists('diversity_dimensions', $data)
            ? $this->decodeDiversityDimensions($data['diversity_dimensions'])
            : $project->diversity_dimensions;

        $project->update([
            'ai_provider_id' => $data['ai_provider_id'] ?? $project->ai_provider_id,
            'name' => $data['name'] ?? $project->name,
            'description' => $data['description'] ?? $project->description,
            'system_prompt' => $data['system_prompt'] ?? $project->system_prompt,
            'schema' => $schema ?? $project->schema,
            'record_count' => $data['record_count'] ?? $project->record_count,
            'chunk_size' => $data['chunk_size'] ?? $project->chunk_size,
            'model' => $data['model'] ?? $project->model,
            'temperature' => $data['temperature'] ?? $project->temperature,
            'max_tokens' => $data['max_tokens'] ?? $project->max_tokens,
            'strategy' => $data['strategy'] ?? $project->strategy,
            'diversity_dimensions' => $diversityDimensions,
            'generation_seed' => array_key_exists('generation_seed', $data) ? $data['generation_seed'] : $project->generation_seed,
            'uniqueness_level' => $data['uniqueness_level'] ?? $project->uniqueness_level ?? 'balanced',
            'semantic_deduplication_enabled' => $data['semantic_deduplication_enabled'] ?? $project->semantic_deduplication_enabled ?? true,
            'semantic_similarity_threshold' => $data['semantic_similarity_threshold'] ?? $project->semantic_similarity_threshold ?? 0.92,
            'replacement_generation_enabled' => $data['replacement_generation_enabled'] ?? $project->replacement_generation_enabled ?? true,
            'evaluation_enabled' => $data['evaluation_enabled'] ?? $project->evaluation_enabled ?? false,
            'evaluation_ai_provider_id' => array_key_exists('evaluation_ai_provider_id', $data) ? $data['evaluation_ai_provider_id'] : $project->evaluation_ai_provider_id,
            'evaluation_model' => array_key_exists('evaluation_model', $data) ? $data['evaluation_model'] : $project->evaluation_model,
            'minimum_quality_score' => $data['minimum_quality_score'] ?? $project->minimum_quality_score ?? 75,
            'critic_enabled' => $data['critic_enabled'] ?? $project->critic_enabled ?? false,
            'critic_ai_provider_id' => array_key_exists('critic_ai_provider_id', $data) ? $data['critic_ai_provider_id'] : $project->critic_ai_provider_id,
            'critic_model' => array_key_exists('critic_model', $data) ? $data['critic_model'] : $project->critic_model,
            'refiner_enabled' => $data['refiner_enabled'] ?? $project->refiner_enabled ?? false,
            'refiner_ai_provider_id' => array_key_exists('refiner_ai_provider_id', $data) ? $data['refiner_ai_provider_id'] : $project->refiner_ai_provider_id,
            'refiner_model' => array_key_exists('refiner_model', $data) ? $data['refiner_model'] : $project->refiner_model,
            'negative_example_ratio' => $data['negative_example_ratio'] ?? $project->negative_example_ratio ?? 0,
            'conversation_enabled' => $data['conversation_enabled'] ?? $project->conversation_enabled ?? false,
            'min_turns' => $data['min_turns'] ?? $project->min_turns ?? 2,
            'max_turns' => $data['max_turns'] ?? $project->max_turns ?? 6,
            'branching_enabled' => $data['branching_enabled'] ?? $project->branching_enabled ?? false,
            'conversation_type' => $data['conversation_type'] ?? $project->conversation_type ?? 'assistant_chat',
            'augmentation_enabled' => $data['augmentation_enabled'] ?? $project->augmentation_enabled ?? false,
            'augmentation_mode' => $data['augmentation_mode'] ?? $project->augmentation_mode ?? 'similar',
            'augmentation_strength' => $data['augmentation_strength'] ?? $project->augmentation_strength ?? 0.5,
            'augmentation_target_count' => array_key_exists('augmentation_target_count', $data) ? $data['augmentation_target_count'] : $project->augmentation_target_count,
            'augmentation_expand_percent' => array_key_exists('augmentation_expand_percent', $data) ? $data['augmentation_expand_percent'] : $project->augmentation_expand_percent,
        ]);

        return $project->fresh();
    }
}
