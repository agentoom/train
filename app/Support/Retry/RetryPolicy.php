<?php

namespace App\Support\Retry;

use App\Enums\AIProviderType;
use App\Models\AIProvider;

final class RetryPolicy
{
    public function forProviderModel(AIProvider $provider): ExponentialBackoffStrategy
    {
        return match ($provider->type) {
            AIProviderType::Anthropic => new ExponentialBackoffStrategy(
                baseDelaySeconds: 2,
                maxDelaySeconds: 120,
                multiplier: 2.0,
                maxAttempts: 4,
            ),
            AIProviderType::OpenRouter => new ExponentialBackoffStrategy(
                baseDelaySeconds: 1,
                maxDelaySeconds: 30,
                multiplier: 1.5,
                maxAttempts: 3,
            ),
            default => new ExponentialBackoffStrategy(
                baseDelaySeconds: 1,
                maxDelaySeconds: 60,
                multiplier: 2.0,
                maxAttempts: 3,
            ),
        };
    }
}
