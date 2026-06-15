<?php

namespace App\DTOs;

final class NegativeExampleResultDTO
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function __construct(
        public readonly array $row,
        public readonly string $failureReason,
    ) {}
}
