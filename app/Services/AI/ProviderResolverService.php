<?php

namespace App\Services\AI;

use App\Contracts\AI\AIProviderInterface;
use App\DTOs\AIProviderConfigDTO;
use App\Models\AIProvider;

class ProviderResolverService
{
    public function __construct(private readonly ProviderFactory $factory) {}

    public function resolve(AIProvider $provider): AIProviderInterface
    {
        $config = new AIProviderConfigDTO(
            type: $provider->type,
            apiKey: $provider->api_key,
            defaultModel: $provider->default_model,
            baseUrl: $provider->base_url,
            label: $provider->label,
        );

        return $this->factory->make($provider->type, $config);
    }
}
