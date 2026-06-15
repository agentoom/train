<?php

namespace App\Services\AI;

use App\DTOs\GenerationResultDTO;
use App\DTOs\PromptConfigDTO;
use App\Models\AIProvider;
use Illuminate\Support\Facades\Log;

class InferenceExecutionService
{
    public function __construct(private readonly ProviderResolverService $resolver) {}

    public function execute(AIProvider $provider, PromptConfigDTO $prompt): GenerationResultDTO
    {
        $instance = $this->resolver->resolve($provider);

        Log::info('Inference execution started', [
            'provider_id' => $provider->id,
            'provider_type' => $provider->type->value,
            'model' => $prompt->model,
        ]);

        $result = $instance->generate($prompt);

        Log::info('Inference execution completed', [
            'provider_id' => $provider->id,
            'model' => $prompt->model,
            'total_tokens' => $result->totalTokens,
            'latency_ms' => $result->latencyMs,
        ]);

        return $result;
    }
}
