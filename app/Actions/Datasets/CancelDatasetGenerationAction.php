<?php

namespace App\Actions\Datasets;

use App\Enums\BatchStatus;
use App\Enums\DatasetStatus;
use App\Models\DatasetVersion;
use Illuminate\Support\Facades\DB;

class CancelDatasetGenerationAction
{
    public function execute(DatasetVersion $version): void
    {
        if (! in_array($version->status, [DatasetStatus::Queued, DatasetStatus::Running], strict: true)) {
            return;
        }

        DB::transaction(function () use ($version): void {
            // Cancel pending/queued batches; running batches are left to finish gracefully
            $version->batches()
                ->whereIn('status', [BatchStatus::Pending->value])
                ->update([
                    'status' => BatchStatus::Cancelled,
                    'cancelled_at' => now(),
                ]);

            $version->update([
                'status' => DatasetStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            $version->datasetProject()->update([
                'status' => DatasetStatus::Cancelled,
            ]);
        });
    }
}
