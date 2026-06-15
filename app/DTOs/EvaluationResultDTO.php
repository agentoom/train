<?php

namespace App\DTOs;

final class EvaluationResultDTO
{
    /**
     * @param  array<string>  $issues
     */
    public function __construct(
        public readonly int $score,
        public readonly string $reasoning,
        public readonly array $issues,
    ) {}
}
