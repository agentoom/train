<?php

namespace App\Livewire\Datasets;

use App\Enums\BatchStatus;
use App\Models\DatasetVersion;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class DatasetProgress extends Component
{
    public int $datasetVersionId;

    public int $datasetProjectId = 0;

    #[On('echo-private:dataset-project.{datasetProjectId},DatasetBatchStarted')]
    #[On('echo-private:dataset-project.{datasetProjectId},DatasetBatchCompleted')]
    #[On('echo-private:dataset-project.{datasetProjectId},DatasetBatchFailed')]
    #[On('echo-private:dataset-project.{datasetProjectId},DatasetGenerationCompleted')]
    public function refreshProgress(): void
    {
        // Triggers re-render
    }

    public function render(): View
    {
        $version = DatasetVersion::with('batches')->find($this->datasetVersionId);
        $batches = $version?->batches ?? collect();
        $total = $batches->count();
        $completed = $batches->where('status', BatchStatus::Completed)->count();
        $failed = $batches->where('status', BatchStatus::Failed)->count();
        $running = $batches->where('status', BatchStatus::Running)->count();

        return view('livewire.datasets.dataset-progress', [
            'version' => $version,
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'running' => $running,
            'percentage' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
        ]);
    }
}
