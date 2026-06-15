<?php

namespace App\Services\Dataset;

use App\DTOs\PromptConfigDTO;
use App\Models\DatasetProject;
use App\Models\DatasetVersion;

class ConversationPromptBuilderService
{
    public function build(DatasetProject $project, int $batchIndex = 0, ?int $recordCount = null, int $totalBatches = 1): PromptConfigDTO
    {
        $minTurns = $project->min_turns ?? 2;
        $maxTurns = $project->max_turns ?? 6;
        $branchingEnabled = (bool) ($project->branching_enabled ?? false);
        $conversationType = $project->conversation_type ?? 'assistant_chat';

        $userPrompt = $this->buildUserPrompt(
            schema: $project->schema,
            recordCount: $recordCount ?? $project->record_count,
            batchIndex: $batchIndex,
            totalBatches: $totalBatches,
            minTurns: $minTurns,
            maxTurns: $maxTurns,
            branchingEnabled: $branchingEnabled,
            conversationType: $conversationType,
        );

        return new PromptConfigDTO(
            systemPrompt: $project->system_prompt ?? 'You are a helpful assistant that generates multi-turn conversational training datasets.',
            userPrompt: $userPrompt,
            model: $project->model ?? 'gpt-4o-mini',
            temperature: $project->temperature ?? 0.8,
            maxTokens: $project->max_tokens ?? 4096,
            schema: null,
            jsonMode: true,
        );
    }

    public function buildFromVersion(DatasetVersion $version, int $batchIndex = 0, ?int $recordCount = null, int $totalBatches = 1): PromptConfigDTO
    {
        $project = $version->datasetProject;
        $minTurns = $project?->min_turns ?? 2;
        $maxTurns = $project?->max_turns ?? 6;
        $branchingEnabled = (bool) ($project?->branching_enabled ?? false);
        $conversationType = $project?->conversation_type ?? 'assistant_chat';

        $userPrompt = $this->buildUserPrompt(
            schema: $version->schema_snapshot,
            recordCount: $recordCount ?? $version->record_count,
            batchIndex: $batchIndex,
            totalBatches: $totalBatches,
            minTurns: $minTurns,
            maxTurns: $maxTurns,
            branchingEnabled: $branchingEnabled,
            conversationType: $conversationType,
        );

        return new PromptConfigDTO(
            systemPrompt: $version->system_prompt_snapshot ?? 'You are a helpful assistant that generates multi-turn conversational training datasets.',
            userPrompt: $userPrompt,
            model: $version->model ?? 'gpt-4o-mini',
            temperature: $project?->temperature ?? 0.8,
            maxTokens: $project?->max_tokens ?? 4096,
            schema: null,
            jsonMode: true,
        );
    }

    /**
     * @param array<string, mixed>|null $schema
     */
    private function buildUserPrompt(
        ?array $schema,
        ?int $recordCount,
        int $batchIndex,
        int $totalBatches,
        int $minTurns,
        int $maxTurns,
        bool $branchingEnabled,
        string $conversationType,
    ): string {
        $count = ($recordCount !== null && $recordCount > 0) ? $recordCount : 1;
        $entry = $count === 1 ? 'conversation' : 'conversations';
        $batchNum = $batchIndex + 1;

        $typeDescription = $this->conversationTypeDescription($conversationType);
        $roleDescription = $this->roleDescription($conversationType);

        $parts = [];

        $parts[] = "Generate EXACTLY {$count} multi-turn {$entry} for training an AI assistant.

OUTPUT FORMAT REQUIREMENTS:

- Return ONLY valid JSON.
- The root element MUST be an ARRAY of exactly {$count} conversation objects.
- Each conversation object MUST have a \"messages\" key containing an array of turn objects.
- Each turn object MUST have \"role\" and \"content\" keys.
- Valid roles: {$roleDescription}
- Do NOT wrap JSON in markdown.
- Do NOT include explanations outside the JSON.
- Output must be parseable by JSON.parse().

EXAMPLE FORMAT:
[
  {
    \"messages\": [
      {\"role\": \"user\", \"content\": \"...\"},
      {\"role\": \"assistant\", \"content\": \"...\"},
      {\"role\": \"user\", \"content\": \"...\"},
      {\"role\": \"assistant\", \"content\": \"...\"}
    ]
  }
]";

        $parts[] = "This is batch {$batchNum} of {$totalBatches}. Each batch must be fully independent and unique.";

        $parts[] = "CONVERSATION TYPE: {$typeDescription}";

        $parts[] = "TURN REQUIREMENTS:
- Each conversation MUST have between {$minTurns} and {$maxTurns} turns (a turn = one user message + one assistant response).
- Conversations must feel natural and realistic.
- Each turn must build meaningfully on the previous ones.
- The conversation must have a clear beginning, middle, and resolution.";

        if ($conversationType === 'tool_usage') {
            $parts[] = 'TOOL USAGE REQUIREMENTS:
- Include realistic tool calls where the assistant invokes tools.
- Use roles: "user", "assistant", "tool", "tool_result".
- Tool calls should use realistic function names and arguments.
- Tool results should be realistic responses that the assistant then uses.

TOOL USAGE EXAMPLE:
[
  {
    "messages": [
      {"role": "user", "content": "What is the weather in Paris?"},
      {"role": "assistant", "content": "Let me check that for you.", "tool_call": {"name": "get_weather", "arguments": {"city": "Paris"}}},
      {"role": "tool_result", "content": "{\"temperature\": 18, \"condition\": \"cloudy\"}"},
      {"role": "assistant", "content": "The weather in Paris is 18°C and cloudy."}
    ]
  }
]';
        }

        if ($branchingEnabled) {
            $parts[] = 'BRANCHING REQUIREMENTS:
- Some conversations may include decision points where the user could have gone in different directions.
- Vary the conversation paths: some should be straightforward, others should involve clarifications, corrections, or topic shifts.
- Avoid all conversations following the same linear pattern.';
        }

        if ($schema) {
            $parts[] = 'ADDITIONAL SCHEMA CONSTRAINTS for each message object:' . "\n" . json_encode($schema, JSON_PRETTY_PRINT);
        }

        $parts[] = 'UNIQUENESS REQUIREMENTS:
- Every conversation must be unique in topic, scenario, and wording.
- Avoid repeating the same questions, answers, or conversation flows.
- Maximize diversity across all generated conversations.';

        return implode("\n\n", $parts);
    }

    private function conversationTypeDescription(string $type): string
    {
        return match ($type) {
            'tool_usage' => 'Tool Usage — conversations where the assistant uses tools/functions to answer user requests.',
            'support_workflow' => 'Support Workflow — customer support conversations with issue identification, troubleshooting, and resolution.',
            'multi_step_reasoning' => 'Multi-Step Reasoning — conversations requiring the assistant to reason through complex problems step by step.',
            default => 'Assistant Chat — general-purpose helpful assistant conversations.',
        };
    }

    private function roleDescription(string $type): string
    {
        if ($type === 'tool_usage') {
            return '"user", "assistant", "tool", "tool_result"';
        }

        return '"user", "assistant"';
    }
}
