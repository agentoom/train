<?php

namespace App\Livewire\Components;

use Illuminate\View\View;
use Livewire\Component;

class StatusBadge extends Component
{
    public string $status = '';

    public string $size = 'sm';

    public function render(): View
    {
        return view('livewire.components.status-badge');
    }
}
