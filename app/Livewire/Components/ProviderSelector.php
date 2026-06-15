<?php

namespace App\Livewire\Components;

use Livewire\Component;

class ProviderSelector extends Component
{
    public ?int $selectedProviderId = null;

    public string $fieldName = 'ai_provider_id';

    /** @var array<int, array<string, mixed>> */
    public array $providers = [];

    public function mount(): void
    {
        $this->providers = \App\Models\AIProvider::where('user_id', auth()->id())
            ->where('is_enabled', true)
            ->orderBy('label')
            ->get(['id', 'label', 'type'])
            ->toArray();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.components.provider-selector');
    }
}
