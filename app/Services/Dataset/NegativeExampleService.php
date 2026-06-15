<?php

namespace App\Services\Dataset;

use App\DTOs\NegativeExampleResultDTO;
use App\DTOs\PromptConfigDTO;
use App\Models\DatasetProject;
use App\Services\AI\InferenceExecutionService;
use Illuminate\Support\Facades\Log;
use Throwable;

class NegativeExampleService
{
    /** Possible failure types for negative examples. */
    private const FAILURE_TYPES = [
        'wrong_tool_selection',
        'malformed_arguments',
        'hallucinated_tool',
        'clarification_required',
        'refusal_scenario',
    ];

    public function __construct(
        private readonly InferenceExecutionService $inferenceService,
    ) {}

    /**
     * Determine how many negative examples to generate for a given target count.
     */
    public function negativeCountForBatch(int $targetCount, DatasetProject $project): int
    {
        $ratio = (int) ($project->negative_example_ratio ?? 0);

        if ($ratio <= 0) {
            return 0;
        }

        return (int) round($targetCount * ($ratio / 100));
    }

    /**
     * Generate a negative (intentionally incorrect) example based on a correct row.
     *
     * Returns null if generation fails gracefully.
     *
     * @param  array<string, mixed>  $correctRow
     * @param  array<string, mixed>|null  $schema
     */
    public function generate(array $correctRow, ?array $schema, DatasetProject $project): ?NegativeExampleResultDTO
    {
        $failureType = self::FAILURE_TYPES[array_rand(self::FAILURE_TYPES)];

        try {
            $prompt = $this->buildPrompt($correctRow, $schema, $project, $failureType);
            $result = $this->inferenceService->execute($project->aiProvider, $prompt);

            $row = $result->rows[0] ?? null;

            if (! is_array($row) || empty($row)) {
                Log::warning('NegativeExampleService: inference returned no usable row', [
                    'project_id' => $project->id,
                    'failure_type' => $failureType,
                ]);

                return null;
            }

            return new NegativeExampleResultDTO(
                row: $row,
                failureReason: $failureType,
            );
        } catch (Throwable $e) {
            Log::warning('NegativeExampleService: generation failed', [
                'project_id' => $project->id,
                'failure_type' => $failureType,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $correctRow
     * @param  array<string, mixed>|null  $schema
     */
    private function buildPrompt(array $correctRow, ?array $schema, DatasetProject $project, string $failureType): PromptConfigDTO
    {
        $failureInstructions = match ($failureType) {
            'wrong_tool_selection' => 'The assistant should call a plausible but incorrect tool — one that exists but is wrong for the situation.',
            'malformed_arguments' => 'The assistant should call the correct tool but with malformed, missing, or wrong-typed arguments.',
            'hallucinated_tool' => 'The assistant should call a tool that does not exist (hallucinated tool name).',
            'clarification_required' => 'The user request is ambiguous and the assistant should ask for clarification instead of acting.',
            'refusal_scenario' => 'The assistant should refuse to complete the request because it is unsafe, out of scope, or impossible.',
            default => 'Introduce a realistic failure or mistake into the example.',
        };

        $systemPrompt = <<<PROMPT
You are a dataset generator creating intentionally incorrect training examples for agent robustness testing.

Your task: given a correct dataset row, produce a realistic NEGATIVE example that demonstrates a specific failure mode.

Failure mode: {$failureType}
Instructions: {$failureInstructions}

Rules:
- Keep the same JSON structure as the original row.
- Make the failure realistic and subtle, not obviously wrong.
- Do NOT add any explanation or commentary.
- Respond ONLY with a single valid JSON object matching the schema.
PROMPT;

        $schemaSection = $schema
            ? "\n\nExpected schema:\n".json_encode($schema, JSON_PRETTY_PRINT)
            : '';

        $userPrompt = "Generate a negative example with failure mode '{$failureType}'.{$schemaSection}"
            ."\n\nOriginal correct row:\n".json_encode($correctRow, JSON_PRETTY_PRINT)
            ."\n\nReturn the negative example as a single JSON object.";

        $model = $project->model ?? 'gpt-4o-mini';

        return new PromptConfigDTO(
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            model: $model,
            temperature: 0.9,
            maxTokens: 2048,
            schema: $schema,
            jsonMode: $schema !== null,
        );
    }
}
