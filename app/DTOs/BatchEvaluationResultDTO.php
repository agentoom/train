<?php

namespace App\DTOs;

final class BatchEvaluationResultDTO
{
    /**
     * @param  array<string, EvaluatorResultDTO>  $evaluatorResults
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly float $overallScore,
        public readonly bool $passed,
        public readonly string $verdict,
        public readonly array $evaluatorResults,
        public readonly array $metadata = [],
    ) {}
}
