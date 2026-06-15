<?php

namespace App\Actions\Providers;

use App\Models\AIProvider;
use App\Models\User;
use App\Services\Settings\ProviderConfigurationService;

class CreateProviderAction
{
    public function __construct(private readonly ProviderConfigurationService $service) {}

    /** @param array<string, mixed> $data */
    public function execute(User $user, array $data): AIProvider
    {
        return $this->service->create($user, $data);
    }
}
