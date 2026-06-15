<?php

namespace App\Actions\Datasets;

use App\Enums\BatchStatus;
use App\Jobs\GenerateDatasetBatchJob;
use App\Models\GenerationBatch;

class RetryGenerationBatchAction
{
    public function execute(GenerationBatch $batch): GenerationBatch
    {
        $batch->update([
            'status' => BatchStatus::Pending,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
            'retry_count' => $batch->retry_count + 1,
        ]);

        GenerateDatasetBatchJob::dispatch($batch->id);

        return $batch->fresh();
    }
}
