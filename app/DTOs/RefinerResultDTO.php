<?php

namespace App\DTOs;

final class RefinerResultDTO
{
    /**
     * @param  array<string, mixed>  $refinedRow
     */
    public function __construct(
        public readonly array $refinedRow,
        public readonly string $model,
    ) {}
}
