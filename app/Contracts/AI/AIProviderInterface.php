<?php

namespace App\Contracts\AI;

use App\DTOs\ConnectionHealthDTO;
use App\DTOs\GenerationResultDTO;
use App\DTOs\PromptConfigDTO;
use App\DTOs\ProviderCapabilitiesDTO;

interface AIProviderInterface
{
    public function generate(PromptConfigDTO $prompt): GenerationResultDTO;

    public function testConnection(): ConnectionHealthDTO;

    public function getCapabilities(): ProviderCapabilitiesDTO;

    /** @return array<int, string> */
    public function getSupportedModels(): array;
}
