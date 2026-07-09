<?php

use App\Enums\BatchStatus;
use App\Enums\DatasetStatus;
use App\Jobs\GenerateDatasetBatchJob;
use App\Livewire\Datasets\DatasetDetail;
use App\Models\DatasetProject;
use App\Models\DatasetProjectLog;
use App\Models\DatasetRow;
use App\Models\DatasetVersion;
use App\Models\GenerationBatch;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

test('it can erase generation history', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => $user->id, 'status' => DatasetStatus::Completed]);
    $version = DatasetVersion::factory()->create(['dataset_project_id' => $project->id]);
    $batch = GenerationBatch::factory()->create(['dataset_version_id' => $version->id]);
    DatasetRow::factory()->create(['dataset_version_id' => $version->id, 'generation_batch_id' => $batch->id]);
    DatasetProjectLog::create([
        'dataset_project_id' => $project->id,
        'level' => 'info',
        'message' => 'Test log',
    ]);

    $this->actingAs($user);

    Livewire::test(DatasetDetail::class, ['project' => $project])
        ->call('eraseHistory');

    $project->refresh();
    expect($project->status)->toBe(DatasetStatus::Draft);
    expect($project->versions()->count())->toBe(0);
    expect(DatasetProjectLog::where('dataset_project_id', $project->id)->count())->toBe(0);
    expect(DatasetRow::count())->toBe(0);
    expect(GenerationBatch::count())->toBe(0);
});

test('it can continue generation when more records are needed', function () {
    Queue::fake();

    $user = User::factory()->create();
    // Project needs 20 records
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'record_count' => 20,
        'status' => DatasetStatus::Failed,
    ]);

    // Version only has 10 records planned
    $version = DatasetVersion::factory()->create([
        'dataset_project_id' => $project->id,
        'record_count' => 10,
        'status' => DatasetStatus::Failed,
    ]);

    // One completed batch
    GenerationBatch::factory()->create([
        'dataset_version_id' => $version->id,
        'batch_number' => 1,
        'offset' => 0,
        'limit' => 5,
        'status' => BatchStatus::Completed,
    ]);

    // One failed batch
    $failedBatch = GenerationBatch::factory()->create([
        'dataset_version_id' => $version->id,
        'batch_number' => 2,
        'offset' => 5,
        'limit' => 5,
        'status' => BatchStatus::Failed,
    ]);

    $this->actingAs($user);

    Livewire::test(DatasetDetail::class, ['project' => $project])
        ->call('continueGeneration');

    $project->refresh();
    $version->refresh();

    expect($project->status)->toBe(DatasetStatus::Queued);
    expect($version->status)->toBe(DatasetStatus::Queued);
    expect($version->record_count)->toBe(20);

    // Should have resumed the failed batch
    $failedBatch->refresh();
    expect($failedBatch->status)->toBe(BatchStatus::Pending);

    // Should have created new batches for the remaining 10 records (20 total - 10 already in version)
    expect($version->batches()->count())->toBeGreaterThan(2);

    Queue::assertPushed(GenerateDatasetBatchJob::class);
});
