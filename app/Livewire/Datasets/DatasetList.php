<?php

namespace App\Livewire\Datasets;

use App\Models\DatasetProject;
use App\Services\Dataset\DatasetSettingsExportService;
use App\Services\Dataset\DatasetSettingsImportService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('Datasets')]
class DatasetList extends Component
{
    use WithFileUploads;

    /** @var list<int> */
    public array $selectedIds = [];

    public bool $showImportModal = false;

    public mixed $importFile = null;

    public ?string $importError = null;

    public function deleteProject(int $id): void
    {
        $project = DatasetProject::findOrFail($id);
        $this->authorize('delete', $project);
        $project->delete();

        $this->selectedIds = array_values(array_filter($this->selectedIds, fn ($i) => $i !== $id));
    }

    public function exportSelected(DatasetSettingsExportService $exportService): StreamedResponse
    {
        $ids = $this->selectedIds;

        $projects = DatasetProject::where('user_id', Auth::id())
            ->whereIn('id', $ids)
            ->get();

        if ($projects->isEmpty()) {
            Flux::toast('Please select at least one dataset to export.', variant: 'warning');

            return response()->streamDownload(fn () => print (''), 'datasets.json');
        }

        $json = $exportService->exportMany($projects);
        $filename = $projects->count() === 1
            ? str($projects->first()->name)->slug()->append('-settings.json')->toString()
            : 'datasets-settings.json';

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename, ['Content-Type' => 'application/json']);
    }

    public function openImportModal(): void
    {
        $this->importFile = null;
        $this->importError = null;
        $this->showImportModal = true;
    }

    public function importSettings(DatasetSettingsImportService $importService): void
    {
        $this->validate(['importFile' => 'required|file|mimes:json,txt|max:2048']);

        try {
            $json = file_get_contents($this->importFile->getRealPath());
            $datasets = $importService->parse($json);
            $created = $importService->import($datasets, Auth::user());

            $this->showImportModal = false;
            $this->importFile = null;
            $this->importError = null;

            $count = count($created);
            Flux::toast(
                trans_choice('{1} 1 dataset imported successfully.|[2,*] :count datasets imported successfully.', $count, ['count' => $count]),
                variant: 'success',
            );
        } catch (\InvalidArgumentException $e) {
            $this->importError = $e->getMessage();
        }
    }

    public function render(): View
    {
        $projects = DatasetProject::where('user_id', Auth::id())
            ->with('aiProvider')
            ->latest()
            ->get();

        return view('livewire.datasets.dataset-list', [
            'projects' => $projects,
        ])->layout('layouts.app', ['title' => __('Datasets')]);
    }
}
