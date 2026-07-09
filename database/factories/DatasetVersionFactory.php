<?php

namespace Database\Factories;

use App\Enums\DatasetStatus;
use App\Models\DatasetProject;
use App\Models\DatasetVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DatasetVersion>
 */
class DatasetVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dataset_project_id' => DatasetProject::factory(),
            'version_number' => 1,
            'ai_provider_id' => null,
            'model' => 'gpt-4o-mini',
            'system_prompt_snapshot' => 'You are a helpful assistant.',
            'schema_snapshot' => null,
            'record_count' => 10,
            'status' => DatasetStatus::Draft,
        ];
    }
}
