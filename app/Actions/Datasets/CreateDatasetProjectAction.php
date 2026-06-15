<?php

namespace App\Actions\Datasets;

use App\Enums\DatasetStatus;
use App\Models\DatasetProject;
use App\Models\User;

class CreateDatasetProjectAction
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

    public function execute(User $user, array $data): DatasetProject
    {
        $schemaRaw = $data['schema'] ?? null;
        $schema = null;

        if ($schemaRaw && is_string($schemaRaw)) {
            $decoded = json_decode($schemaRaw, true);
            $schema = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        } elseif (is_array($schemaRaw)) {
            $schema = $schemaRaw;
        }

        $diversityDimensions = $this->decodeDiversityDimensions($data['diversity_dimensions'] ?? null);

        return DatasetProject::create([
            'user_id' => $user->id,
            'ai_provider_id' => $data['ai_provider_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'system_prompt' => $data['system_prompt'] ?? null,
            'schema' => $schema,
            'record_count' => $data['record_count'] ?? 10,
            'model' => $data['model'] ?? null,
            'temperature' => $data['temperature'] ?? 0.7,
            'max_tokens' => $data['max_tokens'] ?? 2048,
            'strategy' => $data['strategy'] ?? null,
            'diversity_dimensions' => $diversityDimensions,
            'generation_seed' => $data['generation_seed'] ?? null,
            'uniqueness_level' => $data['uniqueness_level'] ?? 'balanced',
            'semantic_deduplication_enabled' => $data['semantic_deduplication_enabled'] ?? true,
            'semantic_similarity_threshold' => $data['semantic_similarity_threshold'] ?? 0.92,
            'replacement_generation_enabled' => $data['replacement_generation_enabled'] ?? true,
            'status' => DatasetStatus::Draft,
        ]);
    }
}
