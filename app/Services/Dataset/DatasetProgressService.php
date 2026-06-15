<?php

namespace App\Services\Dataset;

use App\Enums\BatchStatus;
use App\Enums\DatasetStatus;
use App\Events\DatasetBatchCompleted;
use App\Events\DatasetBatchStarted;
use App\Events\DatasetBatchFailed;
use App\Events\DatasetGenerationCompleted;
use App\Models\GenerationBatch;
use Illuminate\Support\Facades\Log;

class DatasetProgressService
{
    public function markBatchRunning(GenerationBatch $batch): void
    {
        $batch->update([
            'status' => BatchStatus::Running,
            'started_at' => now(),
        ]);

        $version = $batch->datasetVersion;
        if ($version && $version->status === DatasetStatus::Queued) {
            $version->update(['status' => DatasetStatus::Running]);
            $version->datasetProject?->update(['status' => DatasetStatus::Running]);
        }

        if ($version) {
            DatasetBatchStarted::dispatch(
                $version->dataset_project_id,
                $version->id,
                $batch->id,
                $version->datasetProject?->user_id ?? 0,
            );
        }
    }

    /** @return array{string} */
    private function terminalStatuses(): array
    {
        return [BatchStatus::Completed->value, BatchStatus::Failed->value, BatchStatus::Cancelled->value];
    }

    private function allBatchesTerminal(\Illuminate\Support\Collection $batches): bool
    {
        return $batches->every(
            fn (GenerationBatch $b) => in_array($b->status->value, $this->terminalStatuses())
        );
    }

    public function markBatchCompleted(GenerationBatch $batch): void
    {
        $batch->update([
            'status' => BatchStatus::Completed,
            'completed_at' => now(),
        ]);

        $version = $batch->datasetVersion()->with('batches', 'datasetProject')->first();

        if (! $version) {
            return;
        }

        $totalBatches = $version->batches->count();
        $completedBatches = $version->batches->where('status', BatchStatus::Completed)->count();

        Log::info('Batch completed', [
            'batch_id' => $batch->id,
            'completed' => $completedBatches,
            'total' => $totalBatches,
        ]);

        DatasetBatchCompleted::dispatch(
            $version->dataset_project_id,
            $version->id,
            $batch->id,
            $completedBatches,
            $totalBatches,
            $version->datasetProject?->user_id ?? 0,
        );

        if (! $this->allBatchesTerminal($version->batches)) {
            return;
        }

        $hasFailures = $version->batches->contains(
            fn (GenerationBatch $b) => $b->status === BatchStatus::Failed
        );

        if ($hasFailures) {
            $version->update(['status' => DatasetStatus::Failed]);
            $version->datasetProject?->update([
                'status' => DatasetStatus::Failed,
                'failed_at' => now(),
            ]);

            DatasetBatchFailed::dispatch(
                $version->dataset_project_id,
                $version->id,
                $batch->id,
                'One or more batches failed.',
                $version->datasetProject?->user_id ?? 0,
            );

            return;
        }

        $totalRows = $version->rows()->count();
        $version->update(['status' => DatasetStatus::Completed]);
        $version->datasetProject?->update([
            'status' => DatasetStatus::Completed,
            'completed_at' => now(),
        ]);

        DatasetGenerationCompleted::dispatch(
            $version->dataset_project_id,
            $version->id,
            $totalRows,
            $version->datasetProject?->user_id ?? 0,
        );
    }

    public function markBatchFailed(GenerationBatch $batch, string $errorMessage): void
    {
        $batch->update([
            'status' => BatchStatus::Failed,
            'error_message' => $errorMessage,
        ]);

        Log::error('Batch failed', [
            'batch_id' => $batch->id,
            'error' => $errorMessage,
        ]);

        $version = $batch->datasetVersion()->with('batches', 'datasetProject')->first();

        if (! $version) {
            return;
        }

        // Cancel any pending batches so they don't run after a failure
        $this->cancelPendingBatches($version->id);

        // Reload batches after cancellation
        $version->load('batches');

        DatasetBatchFailed::dispatch(
            $version->dataset_project_id,
            $version->id,
            $batch->id,
            $errorMessage,
            $version->datasetProject?->user_id ?? 0,
        );

        if (! $this->allBatchesTerminal($version->batches)) {
            // Other batches are still running; let them finish before finalising the project
            return;
        }

        $version->update(['status' => DatasetStatus::Failed]);
        $version->datasetProject?->update([
            'status' => DatasetStatus::Failed,
            'failed_at' => now(),
        ]);
    }

    public function cancelPendingBatches(int $datasetVersionId): int
    {
        return GenerationBatch::where('dataset_version_id', $datasetVersionId)
            ->where('status', BatchStatus::Pending)
            ->update(['status' => BatchStatus::Cancelled]);
    }
}
