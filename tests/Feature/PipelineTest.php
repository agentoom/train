<?php

use App\Actions\Datasets\StartDatasetGenerationAction;
use App\DTOs\CriticResultDTO;
use App\DTOs\GenerationResultDTO;
use App\DTOs\RefinerResultDTO;
use App\Models\AIProvider;
use App\Models\DatasetProject;
use App\Models\DatasetRow;
use App\Models\User;
use App\Services\AI\InferenceExecutionService;
use App\Services\Pipeline\CriticService;
use App\Services\Pipeline\RefinerService;
use Illuminate\Support\Facades\Event;

// --- CriticService ---

test('CriticService returns null when critic is disabled', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'critic_enabled' => false,
    ]);

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldNotReceive('execute');

    $service = new CriticService($mockInference);
    $result = $service->critique(['text' => 'hello'], null, $project);

    expect($result)->toBeNull();
});

test('CriticService returns CriticResultDTO with feedback when enabled', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'critic_enabled' => true,
        'critic_model' => 'gpt-4o-mini',
    ]);

    $fakeResult = new GenerationResultDTO(
        rows: [],
        promptTokens: 10,
        completionTokens: 20,
        totalTokens: 30,
        latencyMs: 100,
        rawContent: '{"feedback": "The row lacks specificity.", "needs_refinement": true}',
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->once()->andReturn($fakeResult);

    $service = new CriticService($mockInference);
    $result = $service->critique(['text' => 'hello'], null, $project);

    expect($result)->toBeInstanceOf(CriticResultDTO::class)
        ->and($result->feedback)->toBe('The row lacks specificity.')
        ->and($result->needsRefinement)->toBeTrue();
});

test('CriticService returns needsRefinement=false when row is acceptable', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'critic_enabled' => true,
    ]);

    $fakeResult = new GenerationResultDTO(
        rows: [],
        promptTokens: 5,
        completionTokens: 10,
        totalTokens: 15,
        latencyMs: 50,
        rawContent: '{"feedback": "Looks good.", "needs_refinement": false}',
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->once()->andReturn($fakeResult);

    $service = new CriticService($mockInference);
    $result = $service->critique(['text' => 'great row'], null, $project);

    expect($result->needsRefinement)->toBeFalse()
        ->and($result->feedback)->toBe('Looks good.');
});

test('CriticService handles malformed JSON gracefully', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'critic_enabled' => true,
    ]);

    $fakeResult = new GenerationResultDTO(
        rows: [],
        promptTokens: 5,
        completionTokens: 10,
        totalTokens: 15,
        latencyMs: 50,
        rawContent: 'not valid json at all',
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->once()->andReturn($fakeResult);

    $service = new CriticService($mockInference);
    $result = $service->critique(['text' => 'row'], null, $project);

    expect($result)->toBeInstanceOf(CriticResultDTO::class)
        ->and($result->needsRefinement)->toBeFalse();
});

// --- RefinerService ---

test('RefinerService returns null when refiner is disabled', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'refiner_enabled' => false,
    ]);

    $criticResult = new CriticResultDTO(
        feedback: 'Needs work.',
        needsRefinement: true,
        model: 'gpt-4o-mini',
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldNotReceive('execute');

    $service = new RefinerService($mockInference);
    $result = $service->refine(['text' => 'weak row'], $criticResult, null, $project);

    expect($result)->toBeNull();
});

test('RefinerService returns null when needsRefinement is false', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'refiner_enabled' => true,
    ]);

    $criticResult = new CriticResultDTO(
        feedback: 'Looks fine.',
        needsRefinement: false,
        model: 'gpt-4o-mini',
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldNotReceive('execute');

    $service = new RefinerService($mockInference);
    $result = $service->refine(['text' => 'good row'], $criticResult, null, $project);

    expect($result)->toBeNull();
});

test('RefinerService returns refined row when enabled and needsRefinement is true', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'refiner_enabled' => true,
        'refiner_model' => 'gpt-4o',
    ]);

    $criticResult = new CriticResultDTO(
        feedback: 'Too vague.',
        needsRefinement: true,
        model: 'gpt-4o-mini',
    );

    $fakeResult = new GenerationResultDTO(
        rows: [['text' => 'improved and specific row']],
        promptTokens: 20,
        completionTokens: 40,
        totalTokens: 60,
        latencyMs: 200,
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->once()->andReturn($fakeResult);

    $service = new RefinerService($mockInference);
    $result = $service->refine(['text' => 'vague row'], $criticResult, null, $project);

    expect($result)->toBeInstanceOf(RefinerResultDTO::class)
        ->and($result->refinedRow)->toBe(['text' => 'improved and specific row'])
        ->and($result->model)->toBe('gpt-4o');
});

// --- Integration: pipeline disabled — no critic/refiner calls ---

test('GenerateDatasetBatchJob skips pipeline when critic and refiner are disabled', function () {
    config(['queue.default' => 'sync']);

    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'record_count' => 2,
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'Generate test data.',
        'critic_enabled' => false,
        'refiner_enabled' => false,
        'evaluation_enabled' => false,
        'replacement_generation_enabled' => false,
    ]);

    $fakeGenResult = new GenerationResultDTO(
        rows: [['text' => 'row one'], ['text' => 'row two']],
        promptTokens: 10,
        completionTokens: 20,
        totalTokens: 30,
        latencyMs: 100,
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->once()->andReturn($fakeGenResult);
    app()->instance(InferenceExecutionService::class, $mockInference);

    Event::fake();

    $version = app(StartDatasetGenerationAction::class)->execute($project, 2);
    $version->refresh();

    $rows = DatasetRow::where('dataset_version_id', $version->id)->where('is_duplicate', false)->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->every(fn ($r) => $r->generated_by !== null))->toBeTrue()
        ->and($rows->every(fn ($r) => $r->critic_feedback === null))->toBeTrue()
        ->and($rows->every(fn ($r) => $r->refined_by === null))->toBeTrue();
});

// --- Integration: critic + refiner store lineage ---

test('GenerateDatasetBatchJob stores critic feedback and refined_by lineage', function () {
    config(['queue.default' => 'sync']);

    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'record_count' => 1,
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'Generate test data.',
        'critic_enabled' => true,
        'critic_model' => 'gpt-4o-mini',
        'refiner_enabled' => true,
        'refiner_model' => 'gpt-4o',
        'evaluation_enabled' => false,
        'replacement_generation_enabled' => false,
    ]);

    $fakeGenResult = new GenerationResultDTO(
        rows: [['text' => 'weak row']],
        promptTokens: 10,
        completionTokens: 20,
        totalTokens: 30,
        latencyMs: 100,
    );

    $fakeCriticResult = new GenerationResultDTO(
        rows: [],
        promptTokens: 5,
        completionTokens: 10,
        totalTokens: 15,
        latencyMs: 50,
        rawContent: '{"feedback": "Too vague, needs more detail.", "needs_refinement": true}',
    );

    $fakeRefinerResult = new GenerationResultDTO(
        rows: [['text' => 'detailed and specific row']],
        promptTokens: 20,
        completionTokens: 40,
        totalTokens: 60,
        latencyMs: 200,
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->times(3)->andReturn($fakeGenResult, $fakeCriticResult, $fakeRefinerResult);
    app()->instance(InferenceExecutionService::class, $mockInference);

    Event::fake();

    $version = app(StartDatasetGenerationAction::class)->execute($project, 1);
    $version->refresh();

    $row = DatasetRow::where('dataset_version_id', $version->id)->where('is_duplicate', false)->first();

    expect($row)->not->toBeNull()
        ->and($row->payload)->toBe(['text' => 'detailed and specific row'])
        ->and($row->generated_by)->toBe('gpt-4o-mini')
        ->and($row->critic_feedback)->toBe('Too vague, needs more detail.')
        ->and($row->refined_by)->toBe('gpt-4o');
});

// --- Integration: critic runs but needsRefinement=false — refiner not called ---

test('GenerateDatasetBatchJob stores critic feedback but skips refiner when no refinement needed', function () {
    config(['queue.default' => 'sync']);

    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'record_count' => 1,
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'Generate test data.',
        'critic_enabled' => true,
        'critic_model' => 'gpt-4o-mini',
        'refiner_enabled' => true,
        'refiner_model' => 'gpt-4o',
        'evaluation_enabled' => false,
        'replacement_generation_enabled' => false,
    ]);

    $fakeGenResult = new GenerationResultDTO(
        rows: [['text' => 'good row']],
        promptTokens: 10,
        completionTokens: 20,
        totalTokens: 30,
        latencyMs: 100,
    );

    $fakeCriticResult = new GenerationResultDTO(
        rows: [],
        promptTokens: 5,
        completionTokens: 10,
        totalTokens: 15,
        latencyMs: 50,
        rawContent: '{"feedback": "Looks great.", "needs_refinement": false}',
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->twice()->andReturn($fakeGenResult, $fakeCriticResult);
    app()->instance(InferenceExecutionService::class, $mockInference);

    Event::fake();

    $version = app(StartDatasetGenerationAction::class)->execute($project, 1);
    $version->refresh();

    $row = DatasetRow::where('dataset_version_id', $version->id)->where('is_duplicate', false)->first();

    expect($row)->not->toBeNull()
        ->and($row->payload)->toBe(['text' => 'good row'])
        ->and($row->critic_feedback)->toBe('Looks great.')
        ->and($row->refined_by)->toBeNull();
});
