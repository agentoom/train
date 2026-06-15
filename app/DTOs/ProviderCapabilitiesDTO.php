<?php

namespace App\DTOs;

final class ProviderCapabilitiesDTO
{
    public function __construct(
        public readonly bool $supportsJsonMode,
        public readonly bool $supportsStructuredOutput,
        public readonly bool $supportsStreaming,
        public readonly bool $supportsReasoning,
        public readonly bool $supportsTools,
        public readonly int $maxContextWindow,
    ) {}
}
