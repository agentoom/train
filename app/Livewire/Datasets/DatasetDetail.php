<?php

namespace App\Livewire\Datasets;

use App\Actions\Datasets\ContinueDatasetGenerationAction;
use App\Actions\Datasets\EraseDatasetHistoryAction;
use App\Actions\Datasets\CancelDatasetGenerationAction;
use App\Actions\Datasets\RetryGenerationBatchAction;
use App\Actions\Datasets\StartDatasetGenerationAction;
use App\Enums\BatchStatus;
use App\Enums\DatasetStatus;
use App\Models\DatasetProject;
use App\Models\DatasetRow;
use App\Models\GenerationBatch;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Dataset Detail')]
class DatasetDetail extends Component
{
    use WithPagination;

    public DatasetProject $project;

    public ?GenerationBatch $selectedBatchForLog = null;

    public bool $showLogModal = false;

    #[Url]
    public int $perPage = 5;

    #[Url]
    public string $completedFilter = 'all';

    public function mount(DatasetProject $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
    }

    #[On('echo-private:dataset-project.{project.id},DatasetBatchStarted')]
    #[On('echo-private:dataset-project.{project.id},DatasetBatchCompleted')]
    #[On('echo-private:dataset-project.{project.id},DatasetBatchFailed')]
    #[On('echo-private:dataset-project.{project.id},DatasetGenerationCompleted')]
    #[On('echo-private:dataset-project.{project.id},DatasetGenerationStarted')]
    public function refreshDetail(): void
    {
        $this->project->refresh();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage('pendingPage');
        $this->resetPage('runningPage');
        $this->resetPage('failedPage');
        $this->resetPage('completedPage');
    }

    public function updatingCompletedFilter(): void
    {
        $this->resetPage('completedPage');
    }

    public function startGeneration(): void
    {
        $this->authorize('update', $this->project);
        app(StartDatasetGenerationAction::class)->execute($this->project, $this->project->chunk_size ?: null);
    }

    public function retryBatch(int $batchId): void
    {
        $this->authorize('update', $this->project);
        $batch = GenerationBatch::whereHas('datasetVersion', fn ($q) => $q->where('dataset_project_id', $this->project->id))
            ->findOrFail($batchId);
        app(RetryGenerationBatchAction::class)->execute($batch);
    }

    public function cancelGeneration(): void
    {
        $this->authorize('update', $this->project);
        $activeVersion = $this->project->versions()->latest()->first();
        if ($activeVersion) {
            app(CancelDatasetGenerationAction::class)->execute($activeVersion);
        }
    }

    public function showLog(int $batchId): void
    {
        $this->selectedBatchForLog = GenerationBatch::with('logs')->findOrFail($batchId);
        $this->showLogModal = true;
    }

    public function eraseHistory(): void
    {
        $this->authorize('update', $this->project);
        app(EraseDatasetHistoryAction::class)->execute($this->project);
        $this->project->refresh();
    }

    public function continueGeneration(): void
    {
        $this->authorize('update', $this->project);
        app(ContinueDatasetGenerationAction::class)->execute($this->project);
        $this->project->refresh();
    }

    public function render(): View
    {
        $this->project->load(['aiProvider']);

        $latestVersion = $this->project->versions()->latest()->first();

        $totalBatches = 0;
        $completedBatches = 0;
        $failedBatchesCount = 0;
        $qualityMetrics = [];
        $previewRows = collect();
        $totalRows = 0;

        $pendingBatches = collect();
        $runningBatches = collect();
        $failedBatchesList = collect();
        $completedBatchesList = collect();

        if ($latestVersion) {
            $batchBaseQuery = $latestVersion->batches()->withCount('logs');

            // Summary stats for progress bar
            $stats = $latestVersion->batches()
                ->selectRaw('count(*) as total')
                ->selectRaw('count(case when status = ? then 1 end) as completed', [BatchStatus::Completed->value])
                ->selectRaw('count(case when status = ? or status = ? then 1 end) as failed', [BatchStatus::Failed->value, BatchStatus::Cancelled->value])
                ->first();

            $totalBatches = $stats->total;
            $completedBatches = $stats->completed;
            $failedBatchesCount = $stats->failed;

            $totalRows = DatasetRow::where('dataset_version_id', $latestVersion->id)->where('is_duplicate', false)->count();

            $previewRows = DatasetRow::where('dataset_version_id', $latestVersion->id)
                ->where('is_duplicate', false)
                ->orderByDesc('row_index')
                ->paginate(5, ['*'], 'previewPage');

            $pendingBatches = (clone $batchBaseQuery)->where('status', BatchStatus::Pending)
                ->orderBy('batch_number')
                ->paginate($this->perPage, ['*'], 'pendingPage');

            $runningBatches = (clone $batchBaseQuery)->where('status', BatchStatus::Running)
                ->orderBy('batch_number')
                ->paginate($this->perPage, ['*'], 'runningPage');

            $failedBatchesList = (clone $batchBaseQuery)->whereIn('status', [BatchStatus::Failed, BatchStatus::Cancelled])
                ->orderByDesc('updated_at')
                ->paginate($this->perPage, ['*'], 'failedPage');

            $completedQuery = (clone $batchBaseQuery)->where('status', BatchStatus::Completed);

            if ($this->completedFilter === 'full') {
                $completedQuery->where('partially_completed', false);
            } elseif ($this->completedFilter === 'partial') {
                $completedQuery->where('partially_completed', true);
            }

            $completedBatchesList = $completedQuery->orderByDesc('batch_number')
                ->paginate($this->perPage, ['*'], 'completedPage');

            // Quality metrics
            $qualityMetrics = $this->computeQualityMetrics($latestVersion, $totalRows);
        }

        $isCompleted = $this->project->status === DatasetStatus::Completed;

        $canContinue = $latestVersion && ($totalRows < $this->project->record_count);

        return view('livewire.datasets.dataset-detail', [
            'latestVersion' => $latestVersion,
            'pendingBatches' => $pendingBatches,
            'runningBatches' => $runningBatches,
            'failedBatchesList' => $failedBatchesList,
            'completedBatchesList' => $completedBatchesList,
            'totalBatches' => $totalBatches,
            'completedBatches' => $completedBatches,
            'failedBatches' => $failedBatchesCount,
            'previewRows' => $previewRows,
            'totalRows' => $totalRows,
            'isCompleted' => $isCompleted,
            'canContinue' => $canContinue,
            'qualityMetrics' => $qualityMetrics,
        ])->layout('layouts.app', ['title' => $this->project->name]);
    }

    /**
     * Compute dataset quality metrics from batch and row data.
     *
     * @return array<string, mixed>
     */
    private function computeQualityMetrics(mixed $latestVersion, int $uniqueRows): array
    {
        if (! $latestVersion) {
            return [];
        }

        $totalGenerated = DatasetRow::where('dataset_version_id', $latestVersion->id)->count();
        $duplicateCount = DatasetRow::where('dataset_version_id', $latestVersion->id)->where('is_duplicate', true)->count();
        $invalidCount = DatasetRow::where('dataset_version_id', $latestVersion->id)->where('is_valid', false)->where('is_duplicate', false)->count();
        $validCount = $uniqueRows - $invalidCount;

        $duplicatesReplaced = $latestVersion->batches()->sum('regenerated');
        $targetCount = $latestVersion->record_count ?? $this->project->record_count;

        // Evaluation metrics
        $evaluatedRows = DatasetRow::where('dataset_version_id', $latestVersion->id)
            ->whereNotNull('quality_score')
            ->where('is_duplicate', false)
            ->count();
        $evaluationRejected = DatasetRow::where('dataset_version_id', $latestVersion->id)
            ->where('evaluation_failed', true)
            ->count();
        $evaluationErrors = DatasetRow::where('dataset_version_id', $latestVersion->id)
            ->where('evaluation_failed', true)
            ->whereJsonContains('quality_issues', 'evaluation_error')
            ->count();
        $avgQualityScore = $evaluatedRows > 0
            ? DatasetRow::where('dataset_version_id', $latestVersion->id)
                ->whereNotNull('quality_score')
                ->where('is_duplicate', false)
                ->where('evaluation_failed', false)
                ->avg('quality_score')
            : null;

        // Pipeline metrics
        $criticisedRows = DatasetRow::where('dataset_version_id', $latestVersion->id)
            ->whereNotNull('critic_feedback')
            ->where('is_duplicate', false)
            ->count();
        $refinedRows = DatasetRow::where('dataset_version_id', $latestVersion->id)
            ->whereNotNull('refined_by')
            ->where('is_duplicate', false)
            ->count();

        // Negative example metrics
        $negativeExamples = DatasetRow::where('dataset_version_id', $latestVersion->id)
            ->where('expected_behavior', 'incorrect')
            ->count();
        $negativeByType = $negativeExamples > 0
            ? DatasetRow::where('dataset_version_id', $latestVersion->id)
                ->where('expected_behavior', 'incorrect')
                ->whereNotNull('failure_reason')
                ->selectRaw('failure_reason, count(*) as count')
                ->groupBy('failure_reason')
                ->pluck('count', 'failure_reason')
                ->toArray()
            : [];

        $uniquenessScore = $totalGenerated > 0
            ? round((1 - ($duplicateCount / $totalGenerated)) * 100, 1)
            : 100.0;

        $validityScore = $uniqueRows > 0
            ? round(($validCount / $uniqueRows) * 100, 1)
            : 0.0;

        $coverageScore = $targetCount > 0
            ? min(100.0, round(($uniqueRows / $targetCount) * 100, 1))
            : 0.0;

        // Conversation metrics
        $conversationRows = DatasetRow::where('dataset_version_id', $latestVersion->id)
            ->whereNotNull('messages')
            ->where('is_duplicate', false)
            ->count();
        $avgTurnCount = $conversationRows > 0
            ? DatasetRow::where('dataset_version_id', $latestVersion->id)
                ->whereNotNull('turn_count')
                ->where('is_duplicate', false)
                ->avg('turn_count')
            : null;

        // Augmentation metrics
        $sourceCount = $this->project->sources()->count();
        $augmentedRows = $sourceCount > 0
            ? DatasetRow::where('dataset_version_id', $latestVersion->id)
                ->where('is_duplicate', false)
                ->where('is_valid', true)
                ->count()
            : 0;

        return [
            'total_generated' => $totalGenerated,
            'unique_rows' => $uniqueRows,
            'duplicate_count' => $duplicateCount,
            'invalid_count' => $invalidCount,
            'valid_count' => $validCount,
            'regenerated' => $duplicatesReplaced,
            'uniqueness_score' => $uniquenessScore,
            'validity_score' => $validityScore,
            'coverage_score' => $coverageScore,
            'evaluated_rows' => $evaluatedRows,
            'evaluation_rejected' => $evaluationRejected,
            'evaluation_errors' => $evaluationErrors,
            'avg_quality_score' => $avgQualityScore !== null ? round((float) $avgQualityScore, 1) : null,
            'criticised_rows' => $criticisedRows,
            'refined_rows' => $refinedRows,
            'negative_examples' => $negativeExamples,
            'negative_by_type' => $negativeByType,
            'conversation_rows' => $conversationRows,
            'avg_turn_count' => $avgTurnCount !== null ? round((float) $avgTurnCount, 1) : null,
            'source_count' => $sourceCount,
            'augmented_rows' => $augmentedRows,
        ];
    }
}
