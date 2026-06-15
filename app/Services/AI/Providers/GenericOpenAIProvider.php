<?php

namespace App\Services\AI\Providers;

use App\Contracts\AI\AIProviderInterface;
use App\DTOs\AIProviderConfigDTO;
use App\DTOs\ConnectionHealthDTO;
use App\DTOs\GenerationResultDTO;
use App\DTOs\PromptConfigDTO;
use App\DTOs\ProviderCapabilitiesDTO;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenericOpenAIProvider implements AIProviderInterface
{
    public function __construct(private readonly AIProviderConfigDTO $config) {}

    public function generate(PromptConfigDTO $prompt): GenerationResultDTO
    {
        $startTime = hrtime(true);

        try {
            $payload = [
                'model' => $prompt->model,
                'messages' => [
                    ['role' => 'system', 'content' => $prompt->systemPrompt],
                    ['role' => 'user', 'content' => $prompt->userPrompt],
                ],
                'temperature' => $prompt->temperature,
                'max_tokens' => $prompt->maxTokens,
            ];

            if ($prompt->jsonMode) {
                $payload['response_format'] = ['type' => 'json_object'];
            }

            $response = Http::withToken($this->config->apiKey)
                ->baseUrl(rtrim($this->config->baseUrl ?? '', '/'))
                ->timeout(Setting::get('llm_timeout', 90))
                ->post('/chat/completions', $payload)
                ->throw()
                ->json();

            $latencyMs = (int) ((hrtime(true) - $startTime) / 1_000_000);
            $rawContent = $response['choices'][0]['message']['content'] ?? '';
            $usage = $response['usage'] ?? [];

            $rows = [];
            if ($prompt->jsonMode || $prompt->schema !== null) {
                $decoded = json_decode($rawContent, true);
                if (is_array($decoded)) {
                    $rows = [$decoded];
                }
            }

            return new GenerationResultDTO(
                rows: $rows,
                promptTokens: $usage['prompt_tokens'] ?? 0,
                completionTokens: $usage['completion_tokens'] ?? 0,
                totalTokens: $usage['total_tokens'] ?? 0,
                latencyMs: $latencyMs,
                rawContent: $rawContent,
            );
        } catch (Throwable $e) {
            Log::error('Generic OpenAI generation failed', ['error' => $e->getMessage(), 'base_url' => $this->config->baseUrl]);
            throw $e;
        }
    }

    public function testConnection(): ConnectionHealthDTO
    {
        $startTime = hrtime(true);

        try {
            Http::withToken($this->config->apiKey)
                ->baseUrl(rtrim($this->config->baseUrl ?? '', '/'))
                ->timeout(Setting::get('llm_timeout', 90))
                ->post('/chat/completions', [
                    'model' => $this->config->defaultModel ?: 'gpt-3.5-turbo',
                    'messages' => [
                        ['role' => 'user', 'content' => 'Reply with the single word: ok'],
                    ],
                    'max_tokens' => 10,
                ])
                ->throw();

            $latencyMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            return new ConnectionHealthDTO(
                isHealthy: true,
                latencyMs: $latencyMs,
                availableModels: $this->getSupportedModels(),
            );
        } catch (Throwable $e) {
            Log::warning('Generic OpenAI connection test failed', ['error' => $e->getMessage()]);

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
            supportsStructuredOutput: false,
            supportsStreaming: false,
            supportsReasoning: false,
            supportsTools: false,
            maxContextWindow: 4096,
        );
    }

    /** @return array<int, string> */
    public function getSupportedModels(): array
    {
        return array_filter([$this->config->defaultModel ?: 'gpt-3.5-turbo']);
    }
}
