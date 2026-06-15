<?php

namespace App\Actions\Providers;

use App\DTOs\ConnectionHealthDTO;
use App\Models\AIProvider;
use App\Services\AI\ProviderHealthCheckService;

class TestProviderConnectionAction
{
    public function __construct(private readonly ProviderHealthCheckService $healthCheckService) {}

    public function execute(AIProvider $provider): ConnectionHealthDTO
    {
        return $this->healthCheckService->check($provider);
    }
}
