<?php

namespace App\Actions\Datasets;

use App\Enums\BatchStatus;
use App\Enums\DatasetStatus;
use App\Jobs\GenerateDatasetBatchJob;
use App\Models\DatasetProject;
use App\Models\GenerationBatch;
use App\Services\Dataset\GenerationBatchingService;
use Illuminate\Support\Facades\DB;

class ContinueDatasetGenerationAction
{
    public function __construct(private readonly GenerationBatchingService $batchingService) {}

    public function execute(DatasetProject $project): void
    {
        $version = $project->versions()->latest()->first();

        if (! $version) {
            // If no version exists, just start a new generation
            app(StartDatasetGenerationAction::class)->execute($project);

            return;
        }

        DB::transaction(function () use ($project, $version) {
            $project->update(['status' => DatasetStatus::Queued]);
            $version->update(['status' => DatasetStatus::Queued]);

            // 1. Resume existing non-completed batches
            $batchesToResume = $version->batches()
                ->where('status', '!=', BatchStatus::Completed)
                ->get();

            foreach ($batchesToResume as $batch) {
                $batch->update([
                    'status' => BatchStatus::Pending,
                    'error_message' => null,
                ]);
                GenerateDatasetBatchJob::dispatch($batch->id);
            }

            // 2. Add new batches if record count increased
            if ($project->record_count > $version->record_count) {
                $startOffset = (int) $version->record_count;
                $startBatchNumber = (int) $version->batches()->max('batch_number') + 1;

                $newBatches = $this->batchingService->computeBatches(
                    $project->record_count,
                    null,
                    $startOffset,
                    $startBatchNumber
                );

                foreach ($newBatches as $batchData) {
                    $batch = GenerationBatch::create([
                        'dataset_version_id' => $version->id,
                        'batch_number' => $batchData['batch_number'],
                        'offset' => $batchData['offset'],
                        'limit' => $batchData['limit'],
                        'status' => BatchStatus::Pending,
                        'retry_count' => 0,
                    ]);

                    GenerateDatasetBatchJob::dispatch($batch->id);
                }

                // Update version's record count to match the new target
                $version->update(['record_count' => $project->record_count]);
            }
        });
    }
}
