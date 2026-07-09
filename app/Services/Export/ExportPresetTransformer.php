<?php

namespace App\Services\Export;

use App\Enums\ExportFormat;
use App\Models\DatasetRow;

/**
 * Transforms a DatasetRow payload into the target fine-tuning format.
 *
 * Each preset handles three row types:
 *   - Conversation rows  (messages array present)
 *   - Tool-calling rows  (payload contains tool_calls / tools)
 *   - Standard QA rows   (plain payload)
 */
class ExportPresetTransformer
{
    /**
     * @return array<mixed>|null null means skip this row
     */
    public function transform(DatasetRow $row, ExportFormat $format): ?array
    {
        return match ($format) {
            ExportFormat::OpenAI => $this->toOpenAI($row),
            ExportFormat::Anthropic => $this->toAnthropic($row),
            ExportFormat::HuggingFace => $this->toHuggingFace($row),
            ExportFormat::Axolotl => $this->toAxolotl($row),
            ExportFormat::Unsloth => $this->toUnsloth($row),
            ExportFormat::LlamaFactory => $this->toLlamaFactory($row),
            ExportFormat::GenericToolCalling => $this->toGenericToolCalling($row),
            default => $row->payload,
        };
    }

    // ─── OpenAI Chat Fine-Tuning ──────────────────────────────────────────────
    // https://platform.openai.com/docs/guides/fine-tuning

    private function toOpenAI(DatasetRow $row): ?array
    {
        $messages = $this->resolveMessages($row);

        if ($messages !== null) {
            return ['messages' => $messages];
        }

        $payload = $row->payload ?? [];

        if ($this->hasToolCalls($payload)) {
            return ['messages' => $this->payloadToMessages($payload)];
        }

        return ['messages' => $this->qaToMessages($payload)];
    }

    // ─── Anthropic Messages API ───────────────────────────────────────────────

    private function toAnthropic(DatasetRow $row): ?array
    {
        $messages = $this->resolveMessages($row);
        $payload = $row->payload ?? [];

        $system = $payload['system'] ?? null;

        if ($messages !== null) {
            // Extract system from messages if not already in payload
            if ($system === null) {
                foreach ($messages as $m) {
                    if (($m['role'] ?? '') === 'system') {
                        $system = $m['content'] ?? null;
                        break;
                    }
                }
            }
            $anthropicMessages = array_values(array_filter(
                array_map(fn ($m) => $this->toAnthropicMessage($m), $messages),
                fn ($m) => ($m['role'] ?? '') !== 'system',
            ));

            return array_filter([
                'system' => $system,
                'messages' => $anthropicMessages,
            ]);
        }

        if ($this->hasToolCalls($payload)) {
            $msgs = array_values(array_filter(
                $this->payloadToAnthropicMessages($payload),
                fn ($m) => ($m['role'] ?? '') !== 'system',
            ));

            return array_filter([
                'system' => $system,
                'messages' => $msgs,
            ]);
        }

        $msgs = array_values(array_filter(
            $this->qaToAnthropicMessages($payload),
            fn ($m) => ($m['role'] ?? '') !== 'system',
        ));

        return array_filter([
            'system' => $system,
            'messages' => $msgs,
        ]);
    }

    // ─── HuggingFace (ShareGPT / conversations format) ───────────────────────

    private function toHuggingFace(DatasetRow $row): ?array
    {
        $messages = $this->resolveMessages($row);
        $payload = $row->payload ?? [];

        if ($messages !== null) {
            return ['conversations' => array_map(fn ($m) => [
                'from' => $this->hfRole($m['role'] ?? 'user'),
                'value' => $m['content'] ?? '',
            ], $messages)];
        }

        if ($this->hasToolCalls($payload)) {
            return ['conversations' => $this->payloadToHfConversations($payload)];
        }

        return ['conversations' => $this->qaToHfConversations($payload)];
    }

    // ─── Axolotl (ShareGPT format with system field) ─────────────────────────

    private function toAxolotl(DatasetRow $row): ?array
    {
        $hf = $this->toHuggingFace($row);
        $payload = $row->payload ?? [];
        $system = $payload['system'] ?? null;

        if ($hf === null) {
            return null;
        }

        return array_filter([
            'system' => $system,
            'conversations' => $hf['conversations'],
        ]);
    }

    // ─── Unsloth (same as OpenAI chat format) ────────────────────────────────

    private function toUnsloth(DatasetRow $row): ?array
    {
        return $this->toOpenAI($row);
    }

    // ─── LlamaFactory (alpaca-style) ─────────────────────────────────────────

    private function toLlamaFactory(DatasetRow $row): ?array
    {
        $messages = $this->resolveMessages($row);
        $payload = $row->payload ?? [];

        if ($messages !== null) {
            $system = $payload['system'] ?? null;
            $history = [];
            $lastUser = null;
            $lastAssistant = null;

            foreach ($messages as $m) {
                $role = $m['role'] ?? '';
                $content = $m['content'] ?? '';

                if ($role === 'system') {
                    $system = $content;
                } elseif ($role === 'user') {
                    if ($lastUser !== null && $lastAssistant !== null) {
                        $history[] = [$lastUser, $lastAssistant];
                    }
                    $lastUser = $content;
                    $lastAssistant = null;
                } elseif ($role === 'assistant') {
                    $lastAssistant = $content;
                }
            }

            return array_filter([
                'system' => $system,
                'history' => $history ?: null,
                'input' => $lastUser ?? '',
                'output' => $lastAssistant ?? '',
            ]);
        }

        $instruction = $payload['instruction'] ?? $payload['system'] ?? $payload['prompt'] ?? '';
        $input = $payload['input'] ?? $payload['user'] ?? $payload['question'] ?? '';
        $output = $payload['output'] ?? $payload['assistant'] ?? $payload['answer'] ?? '';

        return array_filter([
            'instruction' => $instruction,
            'input' => $input,
            'output' => $output,
        ]);
    }

    // ─── Generic Tool Calling ─────────────────────────────────────────────────

    private function toGenericToolCalling(DatasetRow $row): ?array
    {
        $payload = $row->payload ?? [];
        $messages = $this->resolveMessages($row);

        $tools = $payload['tools'] ?? $payload['functions'] ?? null;

        if ($messages !== null) {
            return array_filter([
                'tools' => $tools,
                'messages' => $messages,
                'expected_behavior' => $row->expected_behavior,
                'failure_reason' => $row->failure_reason,
            ]);
        }

        if ($this->hasToolCalls($payload)) {
            return array_filter([
                'tools' => $tools,
                'messages' => $this->payloadToMessages($payload),
                'expected_behavior' => $row->expected_behavior,
                'failure_reason' => $row->failure_reason,
            ]);
        }

        return array_filter([
            'tools' => $tools,
            'messages' => $this->qaToMessages($payload),
            'expected_behavior' => $row->expected_behavior,
            'failure_reason' => $row->failure_reason,
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** @return array<mixed>|null */
    private function resolveMessages(DatasetRow $row): ?array
    {
        if (! empty($row->messages) && is_array($row->messages)) {
            return $row->messages;
        }

        $payload = $row->payload ?? [];

        if (isset($payload['messages']) && is_array($payload['messages'])) {
            return $payload['messages'];
        }

        return null;
    }

    private function hasToolCalls(array $payload): bool
    {
        return isset($payload['tool_calls']) || isset($payload['tools']) || isset($payload['functions']);
    }

    /** @return array<mixed> */
    private function qaToMessages(array $payload): array
    {
        $messages = [];

        if (! empty($payload['system'])) {
            $messages[] = ['role' => 'system', 'content' => $payload['system']];
        }

        $user = $payload['user'] ?? $payload['question'] ?? $payload['input'] ?? $payload['prompt'] ?? '';
        $assistant = $payload['assistant'] ?? $payload['answer'] ?? $payload['output'] ?? $payload['response'] ?? '';

        if ($user !== '') {
            $messages[] = ['role' => 'user', 'content' => $user];
        }

        if ($assistant !== '') {
            $messages[] = ['role' => 'assistant', 'content' => $assistant];
        }

        return $messages ?: [['role' => 'user', 'content' => json_encode($payload)]];
    }

    /** @return array<mixed> */
    private function payloadToMessages(array $payload): array
    {
        $messages = [];

        if (! empty($payload['system'])) {
            $messages[] = ['role' => 'system', 'content' => $payload['system']];
        }

        if (! empty($payload['user'])) {
            $messages[] = ['role' => 'user', 'content' => $payload['user']];
        }

        if (! empty($payload['tool_calls'])) {
            $messages[] = ['role' => 'assistant', 'content' => null, 'tool_calls' => $payload['tool_calls']];
        }

        if (! empty($payload['tool_result'])) {
            $messages[] = ['role' => 'tool', 'content' => $payload['tool_result']];
        }

        if (! empty($payload['assistant'])) {
            $messages[] = ['role' => 'assistant', 'content' => $payload['assistant']];
        }

        return $messages ?: $this->qaToMessages($payload);
    }

    /** @return array<mixed> */
    private function toAnthropicMessage(array $message): array
    {
        $role = $message['role'] ?? 'user';

        if ($role === 'system') {
            return $message;
        }

        return [
            'role' => $role === 'assistant' ? 'assistant' : 'user',
            'content' => $message['content'] ?? '',
        ];
    }

    /** @return array<mixed> */
    private function qaToAnthropicMessages(array $payload): array
    {
        return array_map(
            fn ($m) => $this->toAnthropicMessage($m),
            $this->qaToMessages($payload),
        );
    }

    /** @return array<mixed> */
    private function payloadToAnthropicMessages(array $payload): array
    {
        return array_map(
            fn ($m) => $this->toAnthropicMessage($m),
            $this->payloadToMessages($payload),
        );
    }

    private function hfRole(string $role): string
    {
        return match ($role) {
            'assistant' => 'gpt',
            'system' => 'system',
            default => 'human',
        };
    }

    /** @return array<mixed> */
    private function qaToHfConversations(array $payload): array
    {
        return array_map(fn ($m) => [
            'from' => $this->hfRole($m['role'] ?? 'user'),
            'value' => $m['content'] ?? '',
        ], $this->qaToMessages($payload));
    }

    /** @return array<mixed> */
    private function payloadToHfConversations(array $payload): array
    {
        return array_map(fn ($m) => [
            'from' => $this->hfRole($m['role'] ?? 'user'),
            'value' => $m['content'] ?? '',
        ], $this->payloadToMessages($payload));
    }
}
