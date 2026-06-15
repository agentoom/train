<?php

namespace App\Livewire\Providers;

use App\Models\AIProvider;
use App\Services\Settings\ProviderConfigurationService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('AI Providers')]
class ProviderList extends Component
{
    public function deleteProvider(int $providerId): void
    {
        $provider = AIProvider::findOrFail($providerId);
        $this->authorize('delete', $provider);

        app(ProviderConfigurationService::class)->delete($provider);

        Flux::toast(variant: 'success', text: 'Provider deleted.');
    }

    public function toggleEnabled(int $providerId): void
    {
        $provider = AIProvider::findOrFail($providerId);
        $this->authorize('update', $provider);

        app(ProviderConfigurationService::class)->toggleEnabled($provider);
    }

    public function render(): View
    {
        $providers = AIProvider::where('user_id', Auth::id())
            ->orderBy('label')
            ->get();

        return view('livewire.providers.provider-list', compact('providers'))
            ->layout('layouts.app', ['title' => __('AI Providers')]);
    }
}
