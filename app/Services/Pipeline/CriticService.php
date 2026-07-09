<?php

namespace App\Services\Pipeline;

use App\DTOs\CriticResultDTO;
use App\DTOs\PromptConfigDTO;
use App\Models\AIProvider;
use App\Models\DatasetProject;
use App\Services\AI\InferenceExecutionService;
use Illuminate\Support\Facades\Log;
use Throwable;

class CriticService
{
    public function __construct(
        private readonly InferenceExecutionService $inferenceService,
    ) {}

    /**
     * Critique a generated row and return feedback.
     *
     * Returns null if critic is disabled or provider is not configured.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $schema
     */
    public function critique(array $row, ?array $schema, DatasetProject $project): ?CriticResultDTO
    {
        if (! $project->critic_enabled) {
            return null;
        }

        $provider = $this->resolveProvider($project);

        if (! $provider) {
            Log::warning('CriticService: critic enabled but no provider configured', [
                'project_id' => $project->id,
            ]);

            return null;
        }

        try {
            $prompt = $this->buildPrompt($row, $schema, $project);
            $result = $this->inferenceService->execute($provider, $prompt);

            return $this->parseResult($result->rawContent ?? json_encode($result->rows[0] ?? []));
        } catch (Throwable $e) {
            Log::warning('CriticService: critique failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            return new CriticResultDTO(
                feedback: 'Critique failed: '.$e->getMessage(),
                needsRefinement: false,
                model: $project->critic_model ?? $project->model ?? 'unknown',
            );
        }
    }

    private function resolveProvider(DatasetProject $project): ?AIProvider
    {
        if ($project->critic_ai_provider_id) {
            return $project->criticAiProvider;
        }

        return $project->aiProvider;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $schema
     */
    private function buildPrompt(array $row, ?array $schema, DatasetProject $project): PromptConfigDTO
    {
        $systemPrompt = <<<'PROMPT'
You are a dataset quality critic. Your job is to review a generated dataset row and identify weaknesses or issues.

Evaluate the row for:
- Accuracy and realism
- Schema compliance
- Instruction adherence
- Diversity and uniqueness
- Training usefulness

Respond ONLY with valid JSON in this exact format:
{
  "feedback": "<concise critique of the row's weaknesses>",
  "needs_refinement": <true|false>
}

Set needs_refinement to true if the row has significant issues that should be fixed.
PROMPT;

        $schemaSection = $schema
            ? "\n\nExpected schema:\n".json_encode($schema, JSON_PRETTY_PRINT)
            : '';

        $instructionSection = $project->system_prompt
            ? "\n\nGeneration instructions:\n".$project->system_prompt
            : '';

        $userPrompt = "Critique this dataset row:{$schemaSection}{$instructionSection}\n\nRow to critique:\n".json_encode($row, JSON_PRETTY_PRINT);

        $model = $project->critic_model ?? $project->model ?? 'gpt-4o-mini';

        return new PromptConfigDTO(
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            model: $model,
            temperature: 0.1,
            maxTokens: 512,
            schema: null,
            jsonMode: true,
        );
    }

    private function parseResult(string $raw): CriticResultDTO
    {
        $raw = trim($raw);

        if (str_starts_with($raw, '```')) {
            $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw) ?? $raw;
            $raw = preg_replace('/\s*```$/', '', $raw) ?? $raw;
        }

        $data = json_decode($raw, true);

        if (! is_array($data)) {
            return new CriticResultDTO(
                feedback: 'Could not parse critique response.',
                needsRefinement: false,
                model: 'unknown',
            );
        }

        return new CriticResultDTO(
            feedback: (string) ($data['feedback'] ?? ''),
            needsRefinement: (bool) ($data['needs_refinement'] ?? false),
            model: 'unknown',
        );
    }
}
