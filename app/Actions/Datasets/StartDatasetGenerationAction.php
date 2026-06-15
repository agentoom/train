<?php

namespace App\Actions\Datasets;

use App\Enums\BatchStatus;
use App\Enums\DatasetStatus;
use App\Events\DatasetGenerationStarted;
use App\Jobs\GenerateDatasetBatchJob;
use App\Models\DatasetProject;
use App\Models\DatasetVersion;
use App\Models\GenerationBatch;
use App\Services\Dataset\GenerationBatchingService;
use Illuminate\Support\Facades\DB;

class StartDatasetGenerationAction
{
    public function __construct(private readonly GenerationBatchingService $batchingService) {}

    public function execute(DatasetProject $project, ?int $batchSize = null): DatasetVersion
    {
        return DB::transaction(function () use ($project, $batchSize) {
            $versionNumber = $project->versions()->max('version_number') + 1;

            $version = DatasetVersion::create([
                'dataset_project_id' => $project->id,
                'version_number' => $versionNumber,
                'ai_provider_id' => $project->ai_provider_id,
                'model' => $project->model,
                'system_prompt_snapshot' => $project->system_prompt,
                'schema_snapshot' => $project->schema,
                'record_count' => $project->record_count,
                'status' => DatasetStatus::Queued,
            ]);

            $project->update(['status' => DatasetStatus::Queued]);

            $batches = $this->batchingService->computeBatches($project->record_count, $batchSize);

            foreach ($batches as $batchData) {
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

            DatasetGenerationStarted::dispatch($project->id, $version->id, $project->user_id);

            return $version;
        });
    }
}
