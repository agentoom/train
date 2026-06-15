<?php

namespace App\DTOs;

final class ConnectionHealthDTO
{
    /**
     * @param  array<int, string>  $availableModels
     */
    public function __construct(
        public readonly bool $isHealthy,
        public readonly int $latencyMs,
        public readonly array $availableModels,
        public readonly ?string $errorMessage = null,
    ) {}
}
