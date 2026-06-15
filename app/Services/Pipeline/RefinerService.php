<?php

namespace App\Services\Pipeline;

use App\DTOs\CriticResultDTO;
use App\DTOs\PromptConfigDTO;
use App\DTOs\RefinerResultDTO;
use App\Models\AIProvider;
use App\Models\DatasetProject;
use App\Services\AI\InferenceExecutionService;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefinerService
{
    public function __construct(
        private readonly InferenceExecutionService $inferenceService,
    ) {}

    /**
     * Rewrite a row based on critic feedback.
     *
     * Returns null if refiner is disabled or provider is not configured.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $schema
     */
    public function refine(array $row, CriticResultDTO $criticResult, ?array $schema, DatasetProject $project): ?RefinerResultDTO
    {
        if (! $project->refiner_enabled) {
            return null;
        }

        if (! $criticResult->needsRefinement) {
            return null;
        }

        $provider = $this->resolveProvider($project);

        if (! $provider) {
            Log::warning('RefinerService: refiner enabled but no provider configured', [
                'project_id' => $project->id,
            ]);

            return null;
        }

        try {
            $model = $project->refiner_model ?? $project->model ?? 'gpt-4o-mini';
            $prompt = $this->buildPrompt($row, $criticResult, $schema, $project, $model);
            $result = $this->inferenceService->execute($provider, $prompt);

            $refinedRow = $result->rows[0] ?? null;

            if (! is_array($refinedRow) || empty($refinedRow)) {
                Log::warning('RefinerService: refiner returned no usable row', [
                    'project_id' => $project->id,
                ]);

                return null;
            }

            return new RefinerResultDTO(
                refinedRow: $refinedRow,
                model: $model,
            );
        } catch (Throwable $e) {
            Log::warning('RefinerService: refinement failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function resolveProvider(DatasetProject $project): ?AIProvider
    {
        if ($project->refiner_ai_provider_id) {
            return $project->refinerAiProvider;
        }

        return $project->aiProvider;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $schema
     */
    private function buildPrompt(array $row, CriticResultDTO $criticResult, ?array $schema, DatasetProject $project, string $model): PromptConfigDTO
    {
        $systemPrompt = <<<'PROMPT'
You are a dataset row refiner. Your job is to rewrite a dataset row to fix the issues identified by a critic.

Preserve the original structure and intent of the row, but address the specific weaknesses mentioned in the critique.
Respond ONLY with the improved row as a single valid JSON object matching the original schema.
Do NOT wrap in an array. Do NOT include any explanation.
PROMPT;

        $schemaSection = $schema
            ? "\n\nExpected schema:\n".json_encode($schema, JSON_PRETTY_PRINT)
            : '';

        $instructionSection = $project->system_prompt
            ? "\n\nGeneration instructions:\n".$project->system_prompt
            : '';

        $userPrompt = "Refine this dataset row based on the critique below.{$schemaSection}{$instructionSection}"
            ."\n\nOriginal row:\n".json_encode($row, JSON_PRETTY_PRINT)
            ."\n\nCritic feedback:\n".$criticResult->feedback
            ."\n\nReturn the improved row as a single JSON object.";

        return new PromptConfigDTO(
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            model: $model,
            temperature: 0.7,
            maxTokens: 2048,
            schema: $schema,
            jsonMode: $schema !== null,
        );
    }
}
