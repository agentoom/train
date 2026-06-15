<?php

namespace App\Support\Cost;

use App\Enums\AIProviderType;

final class CostEstimator
{
    /**
     * Cost per 1000 tokens in USD: [prompt, completion]
     *
     * @var array<string, array{prompt: float, completion: float}>
     */
    private array $rates = [
        'gpt-4o' => ['prompt' => 0.005, 'completion' => 0.015],
        'gpt-4o-mini' => ['prompt' => 0.00015, 'completion' => 0.0006],
        'gpt-4-turbo' => ['prompt' => 0.01, 'completion' => 0.03],
        'gpt-3.5-turbo' => ['prompt' => 0.0005, 'completion' => 0.0015],
        'claude-3-5-sonnet-20241022' => ['prompt' => 0.003, 'completion' => 0.015],
        'claude-3-haiku-20240307' => ['prompt' => 0.00025, 'completion' => 0.00125],
    ];

    public function estimate(string $model, int $promptTokens, int $completionTokens): float
    {
        $rate = $this->rates[$model] ?? ['prompt' => 0.001, 'completion' => 0.002];

        return round(
            ($promptTokens / 1000 * $rate['prompt']) + ($completionTokens / 1000 * $rate['completion']),
            6
        );
    }

    public function estimateFromTotal(AIProviderType $providerType, int $totalTokens): float
    {
        $ratePerThousand = match ($providerType) {
            AIProviderType::Anthropic => 0.008,
            AIProviderType::OpenRouter => 0.002,
            default => 0.003,
        };

        return round($totalTokens / 1000 * $ratePerThousand, 6);
    }
}
