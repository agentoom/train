<?php

namespace App\Livewire\Settings;

use App\Models\Setting;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('General Configuration')]
class Configuration extends Component
{
    #[Validate('required|integer|min:1|max:600')]
    public int $llm_timeout;

    public function mount(): void
    {
        $this->llm_timeout = (int) Setting::get('llm_timeout', 90);
    }

    public function save(): void
    {
        $this->validate();

        Setting::set('llm_timeout', $this->llm_timeout);

        Flux::toast(variant: 'success', text: 'Configuration saved.');
    }

    public function render()
    {
        return view('livewire.settings.configuration');
    }
}
