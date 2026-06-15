<?php

namespace App\DTOs;

final class CriticResultDTO
{
    public function __construct(
        public readonly string $feedback,
        public readonly bool $needsRefinement,
        public readonly string $model,
    ) {}
}
