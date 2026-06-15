<?php

namespace App\DTOs;

final class PromptConfigDTO
{
    /**
     * @param  array<string, mixed>|null  $schema
     */
    public function __construct(
        public readonly string $systemPrompt,
        public readonly string $userPrompt,
        public readonly string $model,
        public readonly float $temperature,
        public readonly int $maxTokens,
        public readonly ?array $schema = null,
        public readonly bool $jsonMode = false,
    ) {}
}
