<?php

namespace App\Console\Commands;

use App\Enums\BatchStatus;
use App\Enums\DatasetStatus;
use App\Models\DatasetVersion;
use App\Models\GenerationBatch;
use App\Services\Dataset\DatasetProgressService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('datasets:cleanup-stuck {--timeout=300 : Seconds after which a running batch is considered stuck} {--dry-run : Preview without making changes}')]
#[Description('Mark stuck running dataset batches as failed and resolve orphaned project statuses')]
class CleanupStuckDatasetsCommand extends Command
{
    public function handle(DatasetProgressService $progressService): int
    {
        $timeoutSeconds = (int) $this->option('timeout');
        $isDryRun = (bool) $this->option('dry-run');

        $stuckBatches = GenerationBatch::with('datasetVersion.datasetProject')
            ->where('status', BatchStatus::Running)
            ->where('started_at', '<', now()->subSeconds($timeoutSeconds))
            ->get();

        if ($stuckBatches->isNotEmpty()) {
            $this->warn("Found {$stuckBatches->count()} stuck batch(es) (running for more than {$timeoutSeconds}s).");

            foreach ($stuckBatches as $batch) {
                $project = $batch->datasetVersion?->datasetProject;
                $this->line("  - Batch #{$batch->id} (project: ".($project?->name ?? 'unknown').", started: {$batch->started_at})");

                if (! $isDryRun) {
                    Log::warning('CleanupStuckDatasetsCommand: marking stuck batch as failed', [
                        'batch_id' => $batch->id,
                        'started_at' => $batch->started_at,
                    ]);

                    $progressService->markBatchFailed($batch, 'Batch timed out or worker crashed.');
                }
            }
        }

        $this->resolveOrphanedVersions($isDryRun);

        if ($isDryRun) {
            $this->info('Dry run — no changes made.');
        } else {
            $this->info('Cleanup complete.');
        }

        return self::SUCCESS;
    }

    private function resolveOrphanedVersions(bool $isDryRun): void
    {
        $terminalStatuses = [BatchStatus::Completed->value, BatchStatus::Failed->value, BatchStatus::Cancelled->value];

        $orphanedVersions = DatasetVersion::with('batches', 'datasetProject')
            ->whereIn('status', [DatasetStatus::Running, DatasetStatus::Queued])
            ->whereDoesntHave('batches', fn ($q) => $q->whereNotIn('status', $terminalStatuses))
            ->get();

        if ($orphanedVersions->isEmpty()) {
            return;
        }

        $this->warn("Found {$orphanedVersions->count()} orphaned version(s) with no active batches.");

        foreach ($orphanedVersions as $version) {
            $hasFailures = $version->batches->contains(fn ($b) => $b->status === BatchStatus::Failed);
            $newStatus = $hasFailures ? DatasetStatus::Failed : DatasetStatus::Completed;

            $this->line("  - Version #{$version->id} → {$newStatus->value}");

            if ($isDryRun) {
                continue;
            }

            if ($hasFailures) {
                $version->update(['status' => DatasetStatus::Failed]);
                $version->datasetProject?->update(['status' => DatasetStatus::Failed, 'failed_at' => now()]);
                Log::warning('CleanupStuckDatasetsCommand: resolved orphaned version as failed', ['version_id' => $version->id]);
            } else {
                $version->update(['status' => DatasetStatus::Completed]);
                $version->datasetProject?->update(['status' => DatasetStatus::Completed, 'completed_at' => now()]);
                Log::info('CleanupStuckDatasetsCommand: resolved orphaned version as completed', ['version_id' => $version->id]);
            }
        }
    }
}
