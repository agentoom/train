<?php

namespace App\Services\AI\Providers;

use App\Contracts\AI\AIProviderInterface;
use App\DTOs\AIProviderConfigDTO;
use App\DTOs\ConnectionHealthDTO;
use App\DTOs\GenerationResultDTO;
use App\DTOs\PromptConfigDTO;
use App\DTOs\ProviderCapabilitiesDTO;
use App\Models\Setting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;
use Throwable;

use function Laravel\Ai\agent;

class OpenAIProvider implements AIProviderInterface
{
    public function __construct(private readonly AIProviderConfigDTO $config) {}

    public function generate(PromptConfigDTO $prompt): GenerationResultDTO
    {
        $startTime = hrtime(true);

        $agentBuilder = agent(instructions: $prompt->systemPrompt);

        if ($prompt->jsonMode || $prompt->schema !== null) {
            $schema = $prompt->schema;
            $agentBuilder = agent(
                instructions: $prompt->systemPrompt,
                schema: fn (JsonSchema $s) => $this->buildSchema($s, $schema),
            );
        }

        $response = $agentBuilder->prompt(
            $prompt->userPrompt,
            provider: Lab::OpenAI,
            model: $prompt->model,
            timeout: Setting::get('llm_timeout', 90),
        );

        $latencyMs = (int) ((hrtime(true) - $startTime) / 1_000_000);
        $rawContent = is_array($response->value ?? null) ? json_encode($response->value) : (string) $response;

        return new GenerationResultDTO(
            rows: is_array($response->value ?? null) ? [$response->value] : [],
            promptTokens: 0,
            completionTokens: 0,
            totalTokens: 0,
            latencyMs: $latencyMs,
            rawContent: $rawContent,
        );
    }

    public function testConnection(): ConnectionHealthDTO
    {
        $startTime = hrtime(true);

        try {
            agent(instructions: 'You are a helpful assistant.')
                ->prompt(
                    'Reply with the single word: ok',
                    provider: Lab::OpenAI,
                    model: $this->config->defaultModel ?: 'gpt-4o-mini',
                    timeout: Setting::get('llm_timeout', 90),
                );

            $latencyMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            return new ConnectionHealthDTO(
                isHealthy: true,
                latencyMs: $latencyMs,
                availableModels: $this->getSupportedModels(),
            );
        } catch (Throwable $e) {
            Log::warning('OpenAI connection test failed', ['error' => $e->getMessage()]);

            return new ConnectionHealthDTO(
                isHealthy: false,
                latencyMs: 0,
                availableModels: [],
                errorMessage: $e->getMessage(),
            );
        }
    }

    public function getCapabilities(): ProviderCapabilitiesDTO
    {
        return new ProviderCapabilitiesDTO(
            supportsJsonMode: true,
            supportsStructuredOutput: true,
            supportsStreaming: true,
            supportsReasoning: false,
            supportsTools: true,
            maxContextWindow: 128000,
        );
    }

    /** @return array<int, string> */
    public function getSupportedModels(): array
    {
        return [
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-4-turbo',
            'gpt-3.5-turbo',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $schema
     * @return array<string, mixed>
     */
    private function buildSchema(JsonSchema $s, ?array $schema): array
    {
        if ($schema === null) {
            return ['data' => $s->string()->required()];
        }

        $result = [];
        foreach ($schema as $key => $type) {
            $result[$key] = $s->string()->required();
        }

        return $result;
    }
}
