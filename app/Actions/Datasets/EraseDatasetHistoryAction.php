<?php

namespace App\Actions\Datasets;

use App\Enums\DatasetStatus;
use App\Models\DatasetProject;
use App\Models\DatasetProjectLog;
use Illuminate\Support\Facades\DB;

class EraseDatasetHistoryAction
{
    /**
     * Erase everything related to the generation of the dataset project.
     */
    public function execute(DatasetProject $project): void
    {
        DB::transaction(function () use ($project) {
            // Delete all versions. This will cascade delete batches, rows, and usages.
            $project->versions()->delete();

            // Delete all project logs.
            DatasetProjectLog::where('dataset_project_id', $project->id)->delete();

            // Reset project status.
            $project->update([
                'status' => DatasetStatus::Draft,
            ]);
        });
    }
}
