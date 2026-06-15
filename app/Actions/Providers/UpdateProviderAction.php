<?php

namespace App\Actions\Providers;

use App\Models\AIProvider;
use App\Services\Settings\ProviderConfigurationService;

class UpdateProviderAction
{
    public function __construct(private readonly ProviderConfigurationService $service) {}

    /** @param array<string, mixed> $data */
    public function execute(AIProvider $provider, array $data): AIProvider
    {
        return $this->service->update($provider, $data);
    }
}
