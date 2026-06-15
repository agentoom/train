<?php

namespace App\Livewire\Providers;

use App\Actions\Providers\TestProviderConnectionAction;
use App\DTOs\ConnectionHealthDTO;
use App\Models\AIProvider;
use Illuminate\View\View;
use Livewire\Component;

class ProviderConnectionTest extends Component
{
    public int $providerId;

    public ?bool $isHealthy = null;

    public ?int $latencyMs = null;

    public ?string $errorMessage = null;

    /** @var array<int, string> */
    public array $availableModels = [];

    public bool $testing = false;

    public function testConnection(): void
    {
        $provider = AIProvider::findOrFail($this->providerId);
        $this->authorize('view', $provider);

        $this->testing = true;
        $this->isHealthy = null;
        $this->errorMessage = null;

        $result = app(TestProviderConnectionAction::class)->execute($provider);

        $this->isHealthy = $result->isHealthy;
        $this->latencyMs = $result->latencyMs;
        $this->availableModels = $result->availableModels;
        $this->errorMessage = $result->errorMessage;
        $this->testing = false;
    }

    public function render(): View
    {
        return view('livewire.providers.provider-connection-test');
    }
}
