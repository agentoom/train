<?php

namespace App\DTOs;

final class GenerationResultDTO
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(
        public readonly array $rows,
        public readonly int $promptTokens,
        public readonly int $completionTokens,
        public readonly int $totalTokens,
        public readonly int $latencyMs,
        public readonly ?string $rawContent = null,
    ) {}
}
