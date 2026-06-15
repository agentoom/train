<?php

namespace App\Livewire\Components;

use Illuminate\View\View;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class ProgressBar extends Component
{
    #[Reactive]
    public int $total = 0;

    #[Reactive]
    public int $completed = 0;

    #[Reactive]
    public string $label = '';

    public function getPercentage(): int
    {
        if ($this->total === 0) {
            return 0;
        }

        return (int) round(($this->completed / $this->total) * 100);
    }

    public function render(): View
    {
        return view('livewire.components.progress-bar', [
            'percentage' => $this->getPercentage(),
        ]);
    }
}
