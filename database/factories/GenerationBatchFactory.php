<?php

namespace Database\Factories;

use App\Models\GenerationBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GenerationBatch>
 */
class GenerationBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dataset_version_id' => \App\Models\DatasetVersion::factory(),
            'batch_number' => 1,
            'offset' => 0,
            'limit' => 10,
            'status' => \App\Enums\BatchStatus::Pending,
            'retry_count' => 0,
            'started_at' => null,
            'completed_at' => null,
            'tokens_used' => null,
            'estimated_cost' => null,
            'error_message' => null,
            'metadata' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status' => \App\Enums\BatchStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => \App\Enums\BatchStatus::Failed,
            'error_message' => 'Test failure',
        ]);
    }
}
