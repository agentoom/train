<?php

namespace App\Services\Evaluation;

use App\DTOs\PromptConfigDTO;
use App\Models\DatasetProject;

class EvaluationPromptBuilderService
{
    /**
     * Build a prompt to evaluate a single dataset row.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $schema
     */
    public function build(array $row, ?array $schema, DatasetProject $project): PromptConfigDTO
    {
        $systemPrompt = <<<'PROMPT'
You are a dataset quality evaluator. Your job is to score a single dataset row on a scale of 0–100.

Evaluate the following criteria:
- schema_correctness: Does the row match the expected schema and field types?
- realism: Is the content realistic and plausible?
- diversity: Is the content meaningfully different from generic examples?
- instruction_compliance: Does the row follow the generation instructions?
- tool_correctness: If tool usage is present, is it correct and well-formed?
- training_usefulness: Would this row be useful for training an AI model?

Respond ONLY with valid JSON in this exact format:
{
  "score": <integer 0-100>,
  "reasoning": "<one or two sentence explanation>",
  "issues": [<list of short issue strings, empty if none>]
}
PROMPT;

        $schemaSection = $schema
            ? "\n\nExpected schema:\n".json_encode($schema, JSON_PRETTY_PRINT)
            : '';

        $instructionSection = $project->system_prompt
            ? "\n\nGeneration instructions:\n".$project->system_prompt
            : '';

        $userPrompt = "Evaluate this dataset row:{$schemaSection}{$instructionSection}\n\nRow to evaluate:\n".json_encode($row, JSON_PRETTY_PRINT);

        $evaluationModel = $project->evaluation_model ?? $project->model ?? 'gpt-4o-mini';

        return new PromptConfigDTO(
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            model: $evaluationModel,
            temperature: 0.1,
            maxTokens: 512,
            schema: null,
            jsonMode: true,
        );
    }
}
