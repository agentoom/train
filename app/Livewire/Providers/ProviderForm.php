<?php

namespace App\Livewire\Providers;

use App\Actions\Providers\CreateProviderAction;
use App\Actions\Providers\UpdateProviderAction;
use App\Enums\AIProviderType;
use App\Models\AIProvider;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('AI Provider')]
class ProviderForm extends Component
{
    public ?AIProvider $provider = null;

    public string $label = '';

    public string $type = '';

    public string $apiKey = '';

    public ?string $baseUrl = null;

    public ?string $defaultModel = null;

    public bool $isEnabled = true;

    public function mount(?AIProvider $provider = null): void
    {
        if ($provider && $provider->exists) {
            $this->authorize('update', $provider);
            $this->provider = $provider;
            $this->label = $provider->label;
            $this->type = $provider->type->value;
            $this->baseUrl = $provider->base_url;
            $this->defaultModel = $provider->default_model;
            $this->isEnabled = $provider->is_enabled;
        }
    }

    public function save(): void
    {
        $this->validate([
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string'],
            'apiKey' => [$this->provider ? 'nullable' : 'required', 'string'],
            'baseUrl' => ['nullable', 'url', 'max:500'],
            'defaultModel' => ['nullable', 'string', 'max:255'],
        ]);

        $data = [
            'label' => $this->label,
            'type' => $this->type,
            'api_key' => $this->apiKey,
            'base_url' => $this->baseUrl,
            'default_model' => $this->defaultModel,
            'is_enabled' => $this->isEnabled,
        ];

        if ($this->provider) {
            $this->authorize('update', $this->provider);
            app(UpdateProviderAction::class)->execute($this->provider, $data);
            Flux::toast(variant: 'success', text: 'Provider updated.');
        } else {
            $this->authorize('create', AIProvider::class);
            app(CreateProviderAction::class)->execute(Auth::user(), $data);
            Flux::toast(variant: 'success', text: 'Provider created.');
        }

        $this->redirect(route('providers.index'), navigate: true);
    }

    /** @return array<int, string> */
    public function getProviderTypes(): array
    {
        return array_map(fn (AIProviderType $t) => $t->value, AIProviderType::cases());
    }

    public function render(): View
    {
        return view('livewire.providers.provider-form', [
            'providerTypes' => AIProviderType::cases(),
        ])->layout('layouts.app', ['title' => $this->provider ? __('Edit Provider') : __('Add Provider')]);
    }
}
