<?php

use App\DTOs\GenerationResultDTO;
use App\DTOs\NegativeExampleResultDTO;
use App\Enums\BatchStatus;
use App\Enums\DatasetStatus;
use App\Models\AIProvider;
use App\Models\DatasetProject;
use App\Models\DatasetRow;
use App\Models\DatasetVersion;
use App\Models\GenerationBatch;
use App\Models\User;
use App\Services\AI\InferenceExecutionService;
use App\Services\Dataset\DatasetProgressService;
use App\Services\Dataset\NegativeExampleService;

// --- NegativeExampleService: negativeCountForBatch ---

test('NegativeExampleService returns 0 when ratio is 0', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'negative_example_ratio' => 0,
    ]);

    $service = new NegativeExampleService(app(InferenceExecutionService::class));

    expect($service->negativeCountForBatch(10, $project))->toBe(0);
});

test('NegativeExampleService computes correct count for 20% ratio', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'negative_example_ratio' => 20,
    ]);

    $service = new NegativeExampleService(app(InferenceExecutionService::class));

    expect($service->negativeCountForBatch(10, $project))->toBe(2);
});

test('NegativeExampleService rounds correctly for 30% ratio', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'negative_example_ratio' => 30,
    ]);

    $service = new NegativeExampleService(app(InferenceExecutionService::class));

    expect($service->negativeCountForBatch(10, $project))->toBe(3);
});

// --- NegativeExampleService: generate ---

test('NegativeExampleService generates a negative example row', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'negative_example_ratio' => 20,
        'model' => 'gpt-4o-mini',
    ]);

    $fakeResult = new GenerationResultDTO(
        rows: [['tool' => 'wrong_tool', 'args' => ['param' => 'value']]],
        promptTokens: 10,
        completionTokens: 20,
        totalTokens: 30,
        latencyMs: 100,
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->once()->andReturn($fakeResult);

    $service = new NegativeExampleService($mockInference);
    $result = $service->generate(['tool' => 'correct_tool', 'args' => []], null, $project);

    expect($result)->toBeInstanceOf(NegativeExampleResultDTO::class)
        ->and($result->row)->toBeArray()
        ->and($result->failureReason)->toBeString()
        ->and($result->failureReason)->not->toBeEmpty();
});

test('NegativeExampleService returns null when inference returns empty rows', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'negative_example_ratio' => 20,
    ]);

    $fakeResult = new GenerationResultDTO(
        rows: [],
        promptTokens: 5,
        completionTokens: 5,
        totalTokens: 10,
        latencyMs: 50,
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->once()->andReturn($fakeResult);

    $service = new NegativeExampleService($mockInference);
    $result = $service->generate(['tool' => 'correct_tool'], null, $project);

    expect($result)->toBeNull();
});

test('NegativeExampleService returns null on inference exception', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'negative_example_ratio' => 20,
    ]);

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->once()->andThrow(new \RuntimeException('API error'));

    $service = new NegativeExampleService($mockInference);
    $result = $service->generate(['tool' => 'correct_tool'], null, $project);

    expect($result)->toBeNull();
});

// --- Integration: GenerateDatasetBatchJob with negative examples ---

test('GenerateDatasetBatchJob generates negative examples when ratio is set', function () {
    Event::fake();

    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'record_count' => 2,
        'negative_example_ratio' => 50,
        'model' => 'gpt-4o-mini',
    ]);
    $version = DatasetVersion::factory()->create([
        'dataset_project_id' => $project->id,
        'version_number' => 1,
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

    $generationResult = new GenerationResultDTO(
        rows: [['tool' => 'search', 'query' => 'weather'], ['tool' => 'search', 'query' => 'news']],
        promptTokens: 10,
        completionTokens: 20,
        totalTokens: 30,
        latencyMs: 100,
    );
    $negativeResult = new GenerationResultDTO(
        rows: [['tool' => 'wrong_tool', 'query' => 'weather']],
        promptTokens: 10,
        completionTokens: 20,
        totalTokens: 30,
        latencyMs: 100,
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->andReturnValues([$generationResult, $negativeResult]);
    app()->instance(InferenceExecutionService::class, $mockInference);

    (new \App\Jobs\GenerateDatasetBatchJob($batch->id))->handle(
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

    $positiveRows = DatasetRow::where('generation_batch_id', $batch->id)
        ->where('expected_behavior', 'correct')
        ->count();
    $negativeRows = DatasetRow::where('generation_batch_id', $batch->id)
        ->where('expected_behavior', 'incorrect')
        ->count();

    expect($positiveRows)->toBe(2)
        ->and($negativeRows)->toBe(1)
        ->and($batch->fresh()->status)->toBe(BatchStatus::Completed);
});

test('GenerateDatasetBatchJob skips negative generation when ratio is 0', function () {
    Event::fake();

    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'record_count' => 2,
        'negative_example_ratio' => 0,
    ]);
    $version = DatasetVersion::factory()->create([
        'dataset_project_id' => $project->id,
        'version_number' => 1,
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
        promptTokens: 10,
        completionTokens: 20,
        totalTokens: 30,
        latencyMs: 100,
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->once()->andReturn($fakeResult);
    app()->instance(InferenceExecutionService::class, $mockInference);

    (new \App\Jobs\GenerateDatasetBatchJob($batch->id))->handle(
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

    $negativeRows = DatasetRow::where('generation_batch_id', $batch->id)
        ->where('expected_behavior', 'incorrect')
        ->count();

    expect($negativeRows)->toBe(0)
        ->and($batch->fresh()->status)->toBe(BatchStatus::Completed);
});

test('negative example rows have failure_reason and expected_behavior set', function () {
    Event::fake();

    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'record_count' => 1,
        'negative_example_ratio' => 100,
        'model' => 'gpt-4o-mini',
    ]);
    $version = DatasetVersion::factory()->create([
        'dataset_project_id' => $project->id,
        'version_number' => 1,
        'ai_provider_id' => $provider->id,
        'status' => DatasetStatus::Queued,
    ]);
    $batch = GenerationBatch::factory()->create([
        'dataset_version_id' => $version->id,
        'batch_number' => 1,
        'offset' => 0,
        'limit' => 1,
        'status' => BatchStatus::Pending,
    ]);

    $generationResult = new GenerationResultDTO(
        rows: [['tool' => 'search', 'query' => 'test']],
        promptTokens: 10,
        completionTokens: 20,
        totalTokens: 30,
        latencyMs: 100,
    );
    $negativeResult = new GenerationResultDTO(
        rows: [['tool' => 'hallucinated_tool', 'query' => 'test']],
        promptTokens: 10,
        completionTokens: 20,
        totalTokens: 30,
        latencyMs: 100,
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->andReturnValues([$generationResult, $negativeResult]);
    app()->instance(InferenceExecutionService::class, $mockInference);

    (new \App\Jobs\GenerateDatasetBatchJob($batch->id))->handle(
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

    $negativeRow = DatasetRow::where('generation_batch_id', $batch->id)
        ->where('expected_behavior', 'incorrect')
        ->first();

    expect($negativeRow)->not->toBeNull()
        ->and($negativeRow->failure_reason)->not->toBeEmpty()
        ->and($negativeRow->is_valid)->toBeTrue();
});
