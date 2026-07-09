<?php

namespace App\Livewire\Components;

use App\Models\AIProvider;
use Illuminate\View\View;
use Livewire\Component;

class ProviderSelector extends Component
{
    public ?int $selectedProviderId = null;

    public string $fieldName = 'ai_provider_id';

    /** @var array<int, array<string, mixed>> */
    public array $providers = [];

    public function mount(): void
    {
        $this->providers = AIProvider::where('user_id', auth()->id())
            ->where('is_enabled', true)
            ->orderBy('label')
            ->get(['id', 'label', 'type'])
            ->toArray();
    }

    public function render(): View
    {
        return view('livewire.components.provider-selector');
    }
}
