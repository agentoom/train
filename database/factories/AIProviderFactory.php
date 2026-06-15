<?php

namespace Database\Factories;

use App\Models\AIProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AIProvider>
 */
class AIProviderFactory extends Factory
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
            'label' => fake()->words(3, true),
            'type' => fake()->randomElement(\App\Enums\AIProviderType::cases())->value,
            'api_key' => fake()->sha256(),
            'base_url' => null,
            'default_model' => 'gpt-4o-mini',
            'is_enabled' => true,
        ];
    }

    public function openai(): static
    {
        return $this->state(['type' => \App\Enums\AIProviderType::OpenAI->value]);
    }

    public function anthropic(): static
    {
        return $this->state(['type' => \App\Enums\AIProviderType::Anthropic->value]);
    }

    public function disabled(): static
    {
        return $this->state(['is_enabled' => false]);
    }
}
