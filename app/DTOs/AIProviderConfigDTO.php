<?php

namespace App\DTOs;

use App\Enums\AIProviderType;

final class AIProviderConfigDTO
{
    public function __construct(
        public readonly AIProviderType $type,
        public readonly string $apiKey,
        public readonly ?string $defaultModel = null,
        public readonly ?string $baseUrl = null,
        public readonly ?string $label = null,
    ) {}
}
