<?php

namespace Database\Factories;

use App\Enums\AIProviderType;
use App\Models\AIProvider;
use App\Models\User;
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
            'user_id' => User::factory(),
            'label' => fake()->words(3, true),
            'type' => fake()->randomElement(AIProviderType::cases())->value,
            'api_key' => fake()->sha256(),
            'base_url' => null,
            'default_model' => 'gpt-4o-mini',
            'is_enabled' => true,
        ];
    }

    public function openai(): static
    {
        return $this->state(['type' => AIProviderType::OpenAI->value]);
    }

    public function anthropic(): static
    {
        return $this->state(['type' => AIProviderType::Anthropic->value]);
    }

    public function disabled(): static
    {
        return $this->state(['is_enabled' => false]);
    }
}
