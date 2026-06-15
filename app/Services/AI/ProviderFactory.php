<?php

namespace App\Services\AI;

use App\Contracts\AI\AIProviderInterface;
use App\DTOs\AIProviderConfigDTO;
use App\Enums\AIProviderType;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\GenericOpenAIProvider;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\AI\Providers\OpenRouterProvider;

class ProviderFactory
{
    public function make(AIProviderType $type, AIProviderConfigDTO $config): AIProviderInterface
    {
        return match ($type) {
            AIProviderType::OpenAI => new OpenAIProvider($config),
            AIProviderType::Anthropic => new AnthropicProvider($config),
            AIProviderType::OpenRouter => new OpenRouterProvider($config),
            AIProviderType::Generic => new GenericOpenAIProvider($config),
        };
    }
}
