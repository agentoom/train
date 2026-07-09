<?php

namespace App\Jobs;

use App\Models\DatasetVersion;
use App\Services\DatasetEvaluation\DatasetEvaluationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunDatasetEvaluationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly int $versionId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(DatasetEvaluationService $evaluationService): void
    {
        $version = DatasetVersion::with('datasetProject')->findOrFail($this->versionId);

        Log::info('RunDatasetEvaluationJob: starting evaluation', [
            'version_id' => $version->id,
        ]);

        $evaluationService->evaluate($version);

        Log::info('RunDatasetEvaluationJob: evaluation complete', [
            'version_id' => $version->id,
        ]);
    }
}
