<?php

namespace App\Livewire\Components;

use App\Enums\BatchStatus;
use App\Models\GenerationBatch;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class GlobalProgress extends Component
{
    public int $userId;

    public function mount(): void
    {
        $this->userId = auth()->id();
    }

    #[On('echo-private:App.Models.User.{userId},DatasetBatchStarted')]
    #[On('echo-private:App.Models.User.{userId},DatasetBatchCompleted')]
    #[On('echo-private:App.Models.User.{userId},DatasetBatchFailed')]
    #[On('echo-private:App.Models.User.{userId},DatasetGenerationCompleted')]
    #[On('echo-private:App.Models.User.{userId},DatasetGenerationStarted')]
    public function refreshGlobalProgress(): void
    {
        // Triggers re-render
    }

    public function render(): View|string
    {
        $userBatchQuery = fn ($q) => $q->whereHas('datasetVersion.datasetProject', fn ($q) => $q->where('user_id', $this->userId));

        $activeBatches = GenerationBatch::whereIn('status', [BatchStatus::Pending, BatchStatus::Running])
            ->where($userBatchQuery)
            ->exists();

        if (! $activeBatches) {
            return '<div></div>';
        }

        // Find all versions that have at least one non-completed batch (pending or running) for this user
        $activeVersionIds = GenerationBatch::whereIn('status', [BatchStatus::Pending, BatchStatus::Running])
            ->where($userBatchQuery)
            ->pluck('dataset_version_id')
            ->unique();

        $stats = GenerationBatch::whereIn('dataset_version_id', $activeVersionIds)
            ->selectRaw('count(*) as total, sum(case when status = ? then 1 else 0 end) as completed', [BatchStatus::Completed->value])
            ->first();

        $total = (int) $stats->total;
        $completed = (int) $stats->completed;
        $percentage = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        return view('livewire.components.global-progress', [
            'total' => $total,
            'completed' => $completed,
            'percentage' => $percentage,
            'activeCount' => $activeVersionIds->count(),
        ]);
    }
}
