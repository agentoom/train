<?php
namespace App\Livewire\Components;

use Illuminate\View\View;
use Livewire\Component;

class JsonPreview extends Component
{
    public string $json = '';

    public string $label = 'JSON Preview';

    public bool $collapsed = false;

    public function toggle(): void
    {
        $this->collapsed = ! $this->collapsed;
    }

    public function getPrettyJson(): string
    {
        if ($this->json === '') {
            return '';
        }

        $decoded = json_decode($this->json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json;
        }

        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function render(): View
    {
        return view('livewire.components.json-preview', [
            'prettyJson' => $this->getPrettyJson(),
        ]);
    }
}
