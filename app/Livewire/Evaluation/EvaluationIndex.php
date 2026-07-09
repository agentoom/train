<?php

namespace App\Livewire\Evaluation;

use App\Jobs\RunDatasetEvaluationJob;
use App\Models\DatasetEvaluationReport;
use App\Models\DatasetVersion;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dataset Evaluation')]
class EvaluationIndex extends Component
{
    public ?int $selectedVersionId = null;

    public bool $isRunning = false;

    public ?string $errorMessage = null;

    public function runEvaluation(): void
    {
        $this->errorMessage = null;

        if (! $this->selectedVersionId) {
            $this->errorMessage = __('Please select a dataset version to evaluate.');

            return;
        }

        $version = DatasetVersion::with('datasetProject')
            ->whereHas('datasetProject', fn ($q) => $q->where('user_id', Auth::id()))
            ->find($this->selectedVersionId);

        if (! $version) {
            $this->errorMessage = __('Dataset version not found or access denied.');

            return;
        }

        $this->isRunning = true;

        try {
            dispatch(new RunDatasetEvaluationJob($version->id));

            $this->isRunning = false;
            $this->selectedVersionId = null;

            $this->dispatch('evaluation-queued');

            Flux::toast(variant: 'success', text: __('Evaluation has been queued and will run in the background.'));
        } catch (\Throwable $e) {
            $this->isRunning = false;
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(): View
    {
        $versions = DatasetVersion::with('datasetProject')
            ->whereHas('datasetProject', fn ($q) => $q->where('user_id', Auth::id()))
            ->latest()
            ->get();

        $reports = DatasetEvaluationReport::with('datasetVersion.datasetProject')
            ->whereHas('datasetVersion.datasetProject', fn ($q) => $q->where('user_id', Auth::id()))
            ->latest()
            ->limit(20)
            ->get();

        return view('livewire.evaluation.evaluation-index', [
            'versions' => $versions,
            'reports' => $reports,
        ])->layout('layouts.app', ['title' => __('Dataset Evaluation')]);
    }
}
