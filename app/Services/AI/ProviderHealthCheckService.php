<?php

namespace App\Services\AI;

use App\DTOs\ConnectionHealthDTO;
use App\Models\AIProvider;

class ProviderHealthCheckService
{
    public function __construct(private readonly ProviderResolverService $resolver) {}

    public function check(AIProvider $provider): ConnectionHealthDTO
    {
        $instance = $this->resolver->resolve($provider);

        return $instance->testConnection();
    }
}
