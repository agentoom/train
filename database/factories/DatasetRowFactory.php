<?php

namespace Database\Factories;

use App\Models\DatasetRow;
use App\Models\DatasetVersion;
use App\Models\GenerationBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DatasetRow>
 */
class DatasetRowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dataset_version_id' => DatasetVersion::factory(),
            'generation_batch_id' => GenerationBatch::factory(),
            'row_index' => $this->faker->unique()->numberBetween(1, 1000),
            'payload' => ['question' => $this->faker->sentence(), 'answer' => $this->faker->paragraph()],
            'is_valid' => true,
            'is_duplicate' => false,
            'content_hash' => $this->faker->sha256(),
            'created_at' => now(),
        ];
    }
}
