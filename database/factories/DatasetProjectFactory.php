<?php

namespace Database\Factories;

use App\Models\DatasetProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DatasetProject>
 */
class DatasetProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'ai_provider_id' => null,
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'system_prompt' => 'You are a helpful assistant that generates structured dataset entries.',
            'schema' => null,
            'record_count' => fake()->numberBetween(10, 100),
            'chunk_size' => 10,
            'evaluation_enabled' => false,
            'evaluation_ai_provider_id' => null,
            'evaluation_model' => null,
            'minimum_quality_score' => 75,
            'critic_enabled' => false,
            'critic_ai_provider_id' => null,
            'critic_model' => null,
            'refiner_enabled' => false,
            'refiner_ai_provider_id' => null,
            'refiner_model' => null,
            'negative_example_ratio' => 0,
            'conversation_enabled' => false,
            'min_turns' => 2,
            'max_turns' => 6,
            'branching_enabled' => false,
            'conversation_type' => null,
            'augmentation_enabled' => false,
            'augmentation_mode' => 'similar',
            'augmentation_strength' => 0.5,
            'augmentation_target_count' => null,
            'augmentation_expand_percent' => null,
            'model' => 'gpt-4o-mini',
            'temperature' => 0.7,
            'max_tokens' => 2048,
            'strategy' => null,
            'status' => \App\Enums\DatasetStatus::Draft,
        ];
    }

    public function withSchema(): static
    {
        return $this->state([
            'schema' => ['type' => 'object', 'properties' => ['text' => ['type' => 'string']]],
        ]);
    }

    public function queued(): static
    {
        return $this->state(['status' => \App\Enums\DatasetStatus::Queued]);
    }

    public function completed(): static
    {
        return $this->state(['status' => \App\Enums\DatasetStatus::Completed, 'completed_at' => now()]);
    }
}
