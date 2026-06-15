<?php

use App\Actions\Datasets\CancelDatasetGenerationAction;
use App\Actions\Datasets\RetryGenerationBatchAction;
use App\Actions\Datasets\StartDatasetGenerationAction;
use App\DTOs\GenerationResultDTO;
use App\Enums\BatchStatus;
use App\Enums\DatasetStatus;
use App\Jobs\GenerateDatasetBatchJob;
use App\Livewire\Datasets\DatasetDetail;
use App\Livewire\Datasets\DatasetProgress;
use App\Models\AIProvider;
use App\Models\DatasetProject;
use App\Models\DatasetRow;
use App\Models\DatasetVersion;
use App\Models\GenerationBatch;
use App\Models\GenerationUsage;
use App\Models\User;
use App\Services\AI\InferenceExecutionService;
use App\Services\Dataset\GenerationBatchingService;
use App\Services\Dataset\DatasetProgressService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

// --- GenerationBatchingService ---

test('GenerationBatchingService computes correct batch ranges', function () {
    $service = new GenerationBatchingService(10);
    $batches = $service->computeBatches(25);

    expect($batches)->toHaveCount(3)
        ->and($batches[0])->toBe(['batch_number' => 1, 'offset' => 0, 'limit' => 10])
        ->and($batches[1])->toBe(['batch_number' => 2, 'offset' => 10, 'limit' => 10])
        ->and($batches[2])->toBe(['batch_number' => 3, 'offset' => 20, 'limit' => 5]);
});

test('GenerationBatchingService handles exact multiple of batch size', function () {
    $service = new GenerationBatchingService(10);
    $batches = $service->computeBatches(20);

    expect($batches)->toHaveCount(2)
        ->and($batches[1]['limit'])->toBe(10);
});

test('GenerationBatchingService handles single record', function () {
    $service = new GenerationBatchingService(10);
    $batches = $service->computeBatches(1);

    expect($batches)->toHaveCount(1)
        ->and($batches[0]['limit'])->toBe(1);
});

test('GenerationBatchingService respects custom batch size', function () {
    $service = new GenerationBatchingService(10);
    $batches = $service->computeBatches(10, 3);

    expect($batches)->toHaveCount(4);
});

// --- StartDatasetGenerationAction ---

test('StartDatasetGenerationAction creates version and dispatches jobs', function () {
    Queue::fake();
    Event::fake();

    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'record_count' => 25,
    ]);

    $version = app(StartDatasetGenerationAction::class)->execute($project, 10);

    expect($version)->toBeInstanceOf(DatasetVersion::class)
        ->and($version->version_number)->toBe(1)
        ->and($version->status)->toBe(DatasetStatus::Queued);

    expect(GenerationBatch::where('dataset_version_id', $version->id)->count())->toBe(3);

    Queue::assertPushed(GenerateDatasetBatchJob::class, 3);
});

test('StartDatasetGenerationAction increments version number on re-run', function () {
    Queue::fake();
    Event::fake();

    $user = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => $user->id, 'record_count' => 5]);

    $v2 = app(StartDatasetGenerationAction::class)->execute($project, 5);
    $v3 = app(StartDatasetGenerationAction::class)->execute($project, 5);

    expect($v2->version_number)->toBe(1)
        ->and($v3->version_number)->toBe(2);
});

test('StartDatasetGenerationAction sets project status to queued', function () {
    Queue::fake();
    Event::fake();

    $user = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => $user->id, 'record_count' => 5]);

    app(StartDatasetGenerationAction::class)->execute($project, 5);

    expect($project->fresh()->status)->toBe(DatasetStatus::Queued);
});

// --- GenerateDatasetBatchJob ---

test('GenerateDatasetBatchJob persists rows and usage record', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'record_count' => 2,
    ]);
    $version = DatasetVersion::factory()->create([
        'dataset_project_id' => $project->id,
        'version_number' => 2,
        'ai_provider_id' => $provider->id,
        'status' => DatasetStatus::Queued,
    ]);
    $batch = GenerationBatch::factory()->create([
        'dataset_version_id' => $version->id,
        'batch_number' => 1,
        'offset' => 0,
        'limit' => 2,
        'status' => BatchStatus::Pending,
    ]);

    $fakeResult = new GenerationResultDTO(
        rows: [['text' => 'row1'], ['text' => 'row2']],
        promptTokens: 100,
        completionTokens: 200,
        totalTokens: 300,
        latencyMs: 500,
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->once()->andReturn($fakeResult);
    app()->instance(InferenceExecutionService::class, $mockInference);

    (new GenerateDatasetBatchJob($batch->id))->handle(
        app(InferenceExecutionService::class),
        app(\App\Services\Dataset\PromptBuilderService::class),
        app(\App\Services\Dataset\ConversationPromptBuilderService::class),
        app(\App\Services\Dataset\DatasetValidationService::class),
        app(DatasetProgressService::class),
        app(\App\Support\Json\JsonRepairer::class),
        app(\App\Support\Cost\CostEstimator::class),
        app(\App\Services\Dataset\HashDeduplicationService::class),
        app(\App\Services\Dataset\SemanticDeduplicationService::class),
        app(\App\Services\Evaluation\DatasetEvaluationService::class),
        app(\App\Services\Pipeline\CriticService::class),
        app(\App\Services\Pipeline\RefinerService::class),
        app(\App\Services\Dataset\NegativeExampleService::class),
        app(\App\Services\Dataset\AugmentationPromptBuilderService::class),
        app(\App\Services\Dataset\DatasetSourceParsingService::class),
    );

    expect(DatasetRow::where('generation_batch_id', $batch->id)->where('is_duplicate', false)->count())->toBe(2);
    expect(GenerationUsage::where('generation_batch_id', $batch->id)->count())->toBe(1);
    expect($batch->fresh()->status)->toBe(BatchStatus::Completed);
});

test('GenerateDatasetBatchJob marks batch and project failed on exception', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'record_count' => 2,
    ]);
    $version = DatasetVersion::factory()->create([
        'dataset_project_id' => $project->id,
        'version_number' => 2,
        'ai_provider_id' => $provider->id,
        'status' => DatasetStatus::Queued,
    ]);
    $batch = GenerationBatch::factory()->create([
        'dataset_version_id' => $version->id,
        'status' => BatchStatus::Pending,
    ]);

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->once()->andThrow(new \RuntimeException('API error'));
    app()->instance(InferenceExecutionService::class, $mockInference);

    (new GenerateDatasetBatchJob($batch->id))->handle(
        app(InferenceExecutionService::class),
        app(\App\Services\Dataset\PromptBuilderService::class),
        app(\App\Services\Dataset\ConversationPromptBuilderService::class),
        app(\App\Services\Dataset\DatasetValidationService::class),
        app(DatasetProgressService::class),
        app(\App\Support\Json\JsonRepairer::class),
        app(\App\Support\Cost\CostEstimator::class),
        app(\App\Services\Dataset\HashDeduplicationService::class),
        app(\App\Services\Dataset\SemanticDeduplicationService::class),
        app(\App\Services\Evaluation\DatasetEvaluationService::class),
        app(\App\Services\Pipeline\CriticService::class),
        app(\App\Services\Pipeline\RefinerService::class),
        app(\App\Services\Dataset\NegativeExampleService::class),
        app(\App\Services\Dataset\AugmentationPromptBuilderService::class),
        app(\App\Services\Dataset\DatasetSourceParsingService::class),
    );

    expect($batch->fresh()->status)->toBe(BatchStatus::Failed)
        ->and($batch->fresh()->error_message)->toBe('API error');
    expect($project->fresh()->status)->toBe(DatasetStatus::Failed);
});

test('GenerateDatasetBatchJob updates status to failed via failed method', function () {
    $batch = GenerationBatch::factory()->create(['status' => BatchStatus::Running]);

    $job = new GenerateDatasetBatchJob($batch->id);
    $job->failed(new \Exception('Direct failure'));

    expect($batch->fresh()->status)->toBe(BatchStatus::Failed)
        ->and($batch->fresh()->error_message)->toBe('Direct failure');
});

test('GenerateDatasetBatchJob is idempotent for completed batches', function () {
    $batch = GenerationBatch::factory()->completed()->create();

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldNotReceive('execute');
    app()->instance(InferenceExecutionService::class, $mockInference);

    (new GenerateDatasetBatchJob($batch->id))->handle(
        app(InferenceExecutionService::class),
        app(\App\Services\Dataset\PromptBuilderService::class),
        app(\App\Services\Dataset\ConversationPromptBuilderService::class),
        app(\App\Services\Dataset\DatasetValidationService::class),
        app(DatasetProgressService::class),
        app(\App\Support\Json\JsonRepairer::class),
        app(\App\Support\Cost\CostEstimator::class),
        app(\App\Services\Dataset\HashDeduplicationService::class),
        app(\App\Services\Dataset\SemanticDeduplicationService::class),
        app(\App\Services\Evaluation\DatasetEvaluationService::class),
        app(\App\Services\Pipeline\CriticService::class),
        app(\App\Services\Pipeline\RefinerService::class),
        app(\App\Services\Dataset\NegativeExampleService::class),
        app(\App\Services\Dataset\AugmentationPromptBuilderService::class),
        app(\App\Services\Dataset\DatasetSourceParsingService::class),
    );

    expect($batch->fresh()->status)->toBe(BatchStatus::Completed);
});

// --- RetryGenerationBatchAction ---

test('RetryGenerationBatchAction resets batch status and re-queues job', function () {
    Queue::fake();

    $batch = GenerationBatch::factory()->failed()->create(['retry_count' => 1]);

    $result = app(RetryGenerationBatchAction::class)->execute($batch);

    expect($result->status)->toBe(BatchStatus::Pending)
        ->and($result->retry_count)->toBe(2)
        ->and($result->error_message)->toBeNull();

    Queue::assertPushed(GenerateDatasetBatchJob::class, fn ($job) => $job->batchId === $batch->id);
});

// --- DatasetProgressService ---

test('DatasetProgressService marks all batches completed and fires completion event', function () {
    Event::fake();

    $user = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => $user->id, 'status' => DatasetStatus::Running]);
    $version = DatasetVersion::factory()->create([
        'dataset_project_id' => $project->id,
        'status' => DatasetStatus::Running,
    ]);
    $batch = GenerationBatch::factory()->create([
        'dataset_version_id' => $version->id,
        'status' => BatchStatus::Running,
    ]);

    app(DatasetProgressService::class)->markBatchCompleted($batch);

    expect($batch->fresh()->status)->toBe(BatchStatus::Completed);
    expect($version->fresh()->status)->toBe(DatasetStatus::Completed);
    expect($project->fresh()->status)->toBe(DatasetStatus::Completed);

    Event::assertDispatched(\App\Events\DatasetBatchCompleted::class);
    Event::assertDispatched(\App\Events\DatasetGenerationCompleted::class);
});

test('DatasetProgressService cancelPendingBatches only cancels pending', function () {
    $version = DatasetVersion::factory()->create();
    $pending = GenerationBatch::factory()->create(['dataset_version_id' => $version->id, 'status' => BatchStatus::Pending]);
    $running = GenerationBatch::factory()->create(['dataset_version_id' => $version->id, 'status' => BatchStatus::Running]);

    app(DatasetProgressService::class)->cancelPendingBatches($version->id);

    expect($pending->fresh()->status)->toBe(BatchStatus::Cancelled)
        ->and($running->fresh()->status)->toBe(BatchStatus::Running);
});

// --- Routes ---

test('dataset detail route requires auth', function () {
    $project = DatasetProject::factory()->create();
    $this->get(route('datasets.show', $project))->assertRedirect(route('login'));
});

test('authenticated user can view their dataset detail', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('datasets.show', $project))
        ->assertOk();
});

test('user cannot view another users dataset detail', function () {
    $user = User::factory()->create();
    $other = DatasetProject::factory()->create();

    $this->actingAs($user)
        ->get(route('datasets.show', $other))
        ->assertForbidden();
});

// --- Livewire DatasetDetail ---

test('DatasetDetail startGeneration dispatches jobs', function () {
    Queue::fake();
    Event::fake();

    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'record_count' => 5,
    ]);

    Livewire::actingAs($user)
        ->test(DatasetDetail::class, ['project' => $project])
        ->call('startGeneration');

    Queue::assertPushed(GenerateDatasetBatchJob::class);
});

test('DatasetDetail retryBatch re-queues failed batch', function () {
    Queue::fake();

    $user = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => $user->id]);
    $version = DatasetVersion::factory()->create(['dataset_project_id' => $project->id]);
    $batch = GenerationBatch::factory()->failed()->create(['dataset_version_id' => $version->id]);

    Livewire::actingAs($user)
        ->test(DatasetDetail::class, ['project' => $project])
        ->call('retryBatch', $batch->id);

    Queue::assertPushed(GenerateDatasetBatchJob::class, fn ($job) => $job->batchId === $batch->id);
});

test('DatasetDetail cancelGeneration cancels pending batches', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => $user->id, 'status' => DatasetStatus::Running]);
    $version = DatasetVersion::factory()->create([
        'dataset_project_id' => $project->id,
        'status' => DatasetStatus::Running,
    ]);
    $batch = GenerationBatch::factory()->create([
        'dataset_version_id' => $version->id,
        'status' => BatchStatus::Pending,
    ]);

    Livewire::actingAs($user)
        ->test(DatasetDetail::class, ['project' => $project])
        ->call('cancelGeneration');

    expect($batch->fresh()->status)->toBe(BatchStatus::Cancelled);
    expect($project->fresh()->status)->toBe(DatasetStatus::Cancelled);
});

// --- CancelDatasetGenerationAction ---

test('CancelDatasetGenerationAction cancels pending batches and version', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => $user->id, 'status' => DatasetStatus::Running]);
    $version = DatasetVersion::factory()->create([
        'dataset_project_id' => $project->id,
        'status' => DatasetStatus::Running,
    ]);
    $pending = GenerationBatch::factory()->create(['dataset_version_id' => $version->id, 'status' => BatchStatus::Pending]);
    $running = GenerationBatch::factory()->create(['dataset_version_id' => $version->id, 'status' => BatchStatus::Running]);

    app(CancelDatasetGenerationAction::class)->execute($version);

    expect($pending->fresh()->status)->toBe(BatchStatus::Cancelled)
        ->and($pending->fresh()->cancelled_at)->not->toBeNull()
        ->and($running->fresh()->status)->toBe(BatchStatus::Running) // running batch left to finish gracefully
        ->and($version->fresh()->status)->toBe(DatasetStatus::Cancelled)
        ->and($version->fresh()->cancelled_at)->not->toBeNull()
        ->and($project->fresh()->status)->toBe(DatasetStatus::Cancelled);
});

test('CancelDatasetGenerationAction is idempotent on already-cancelled version', function () {
    $version = DatasetVersion::factory()->create(['status' => DatasetStatus::Cancelled]);

    // Should not throw, should be a no-op
    app(CancelDatasetGenerationAction::class)->execute($version);

    expect($version->fresh()->status)->toBe(DatasetStatus::Cancelled);
});

test('CancelDatasetGenerationAction does not cancel completed version', function () {
    $version = DatasetVersion::factory()->create(['status' => DatasetStatus::Completed]);

    app(CancelDatasetGenerationAction::class)->execute($version);

    expect($version->fresh()->status)->toBe(DatasetStatus::Completed);
});

// --- E2E Integration Test ---

test('full generation happy path: version created, batches executed, rows and usage persisted, project completed', function () {
    // Use sync queue so jobs run inline without Queue::fake()
    config(['queue.default' => 'sync']);

    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'record_count' => 4,
        'model' => 'gpt-4o',
        'system_prompt' => 'Generate test data.',
    ]);

    // Fake the inference service to return distinct rows per batch (avoid semantic dedup)
    $fakeResult1 = new GenerationResultDTO(
        rows: [['text' => 'alpha unique sentence about shipping'], ['text' => 'beta unique sentence about returns']],
        promptTokens: 50,
        completionTokens: 100,
        totalTokens: 150,
        latencyMs: 200,
    );
    $fakeResult2 = new GenerationResultDTO(
        rows: [['text' => 'gamma unique sentence about payment methods'], ['text' => 'delta unique sentence about refunds policy']],
        promptTokens: 50,
        completionTokens: 100,
        totalTokens: 150,
        latencyMs: 200,
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->twice()->andReturn($fakeResult1, $fakeResult2);
    app()->instance(InferenceExecutionService::class, $mockInference);

    Event::fake([
        \App\Events\DatasetGenerationStarted::class,
        \App\Events\DatasetBatchCompleted::class,
        \App\Events\DatasetGenerationCompleted::class,
    ]);

    // Execute: start generation with batch size 2 → 2 batches
    $version = app(StartDatasetGenerationAction::class)->execute($project, 2);

    // Refresh to get post-job state
    $version->refresh();

    // Assert DatasetVersion created with auto-generated seed
    expect($version->version_number)->toBe(1)
        ->and($version->status)->toBe(DatasetStatus::Completed)
        ->and($version->generation_seed)->not->toBeNull();

    // Assert all batches completed
    $batches = $version->batches()->get();
    expect($batches)->toHaveCount(2);
    $batches->each(fn ($b) => expect($b->status)->toBe(BatchStatus::Completed));

    // Assert snapshots captured
    $batches->each(function ($b) use ($provider, $project) {
        expect($b->provider_snapshot)->toBeArray()
            ->and($b->provider_snapshot['id'])->toBe($provider->id)
            ->and($b->model_snapshot)->toBe($project->model)
            ->and($b->prompt_snapshot)->not->toBeNull();
    });

    // Assert dataset rows persisted (2 unique rows × 2 batches = 4 non-duplicate rows)
    expect(DatasetRow::where('dataset_version_id', $version->id)->where('is_duplicate', false)->count())->toBe(4);

    // Assert generation usages persisted (1 per batch)
    expect(GenerationUsage::where('dataset_version_id', $version->id)->count())->toBe(2);

    // Assert project marked completed
    expect($project->fresh()->status)->toBe(DatasetStatus::Completed);

    // Assert events fired
    Event::assertDispatched(\App\Events\DatasetGenerationStarted::class);
    Event::assertDispatched(\App\Events\DatasetBatchCompleted::class);
    Event::assertDispatched(\App\Events\DatasetGenerationCompleted::class);
});

// --- Reproducibility: generation_seed auto-generated ---

test('DatasetVersion auto-generates generation_seed on creation', function () {
    $v1 = DatasetVersion::factory()->create();
    $v2 = DatasetVersion::factory()->create();

    expect($v1->generation_seed)->not->toBeNull()
        ->and($v2->generation_seed)->not->toBeNull()
        ->and($v1->generation_seed)->not->toBe($v2->generation_seed);
});

test('DatasetVersion respects explicitly provided generation_seed', function () {
    $version = DatasetVersion::factory()->create(['generation_seed' => 42]);

    expect($version->generation_seed)->toBe(42);
});

// --- Livewire DatasetProgress ---

test('DatasetProgress renders with correct progress data', function () {
    $user = User::factory()->create();
    $version = DatasetVersion::factory()->create();
    GenerationBatch::factory()->completed()->create(['dataset_version_id' => $version->id]);
    GenerationBatch::factory()->create(['dataset_version_id' => $version->id, 'status' => BatchStatus::Pending]);

    Livewire::actingAs($user)
        ->test(DatasetProgress::class, ['datasetVersionId' => $version->id])
        ->assertSet('datasetVersionId', $version->id)
        ->assertSeeHtml('50%');
});
