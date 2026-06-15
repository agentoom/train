<?php

namespace App\DTOs;

final class EvaluatorResultDTO
{
    /**
     * @param  array<string, mixed>  $details
     * @param  string  $status  One of: applicable, not_applicable
     * @param  string|null  $notApplicableReason  Human-readable reason when status = not_applicable
     * @param  int  $weight  Relative weight used in weighted average (ignored when not_applicable)
     */
    public function __construct(
        public readonly string $evaluator,
        public readonly float $score,
        public readonly bool $passed,
        public readonly string $summary,
        public readonly array $details = [],
        public readonly string $status = 'applicable',
        public readonly ?string $notApplicableReason = null,
        public readonly int $weight = 1,
    ) {}

    public function isApplicable(): bool
    {
        return $this->status === 'applicable';
    }
}
