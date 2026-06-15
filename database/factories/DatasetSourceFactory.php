<?php

namespace Database\Factories;

use App\Models\DatasetSource;
use App\Models\DatasetProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DatasetSource>
 */
class DatasetSourceFactory extends Factory
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
            'original_filename' => $this->faker->word() . '.jsonl',
            'file_path' => 'dataset-sources/' . $this->faker->uuid() . '.jsonl',
            'source_type' => 'jsonl',
            'parsed_schema' => null,
            'row_count' => $this->faker->numberBetween(10, 200),
            'metadata' => null,
        ];
    }
}
