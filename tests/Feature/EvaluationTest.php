<?php

use App\Actions\Datasets\StartDatasetGenerationAction;
use App\DTOs\EvaluationResultDTO;
use App\DTOs\GenerationResultDTO;
use App\Models\AIProvider;
use App\Models\DatasetProject;
use App\Models\DatasetRow;
use App\Models\User;
use App\Services\AI\InferenceExecutionService;
use App\Services\Evaluation\DatasetEvaluationService;
use App\Services\Evaluation\EvaluationPromptBuilderService;
use Illuminate\Support\Facades\Event;

// --- EvaluationPromptBuilderService ---

test('EvaluationPromptBuilderService builds a prompt with row and schema', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'evaluation_model' => 'gpt-4o-mini',
        'system_prompt' => 'Generate customer support data.',
    ]);

    $builder = new EvaluationPromptBuilderService();
    $prompt = $builder->build(
        row: ['question' => 'How do I return an item?', 'answer' => 'Visit our returns page.'],
        schema: ['question' => 'string', 'answer' => 'string'],
        project: $project,
    );

    expect($prompt->model)->toBe('gpt-4o-mini')
        ->and($prompt->temperature)->toBe(0.1)
        ->and($prompt->jsonMode)->toBeTrue()
        ->and($prompt->systemPrompt)->toContain('quality evaluator')
        ->and($prompt->userPrompt)->toContain('How do I return an item?')
        ->and($prompt->userPrompt)->toContain('Generate customer support data.');
});

test('EvaluationPromptBuilderService falls back to project model when evaluation_model is null', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'model' => 'claude-3-haiku',
        'evaluation_model' => null,
    ]);

    $builder = new EvaluationPromptBuilderService();
    $prompt = $builder->build(row: ['text' => 'hello'], schema: null, project: $project);

    expect($prompt->model)->toBe('claude-3-haiku');
});

// --- DatasetEvaluationService: parsing ---

test('DatasetEvaluationService parses a valid JSON evaluation response', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'evaluation_enabled' => true,
        'minimum_quality_score' => 75,
    ]);

    $fakeResult = new GenerationResultDTO(
        rows: [],
        promptTokens: 10,
        completionTokens: 20,
        totalTokens: 30,
        latencyMs: 100,
        rawContent: '{"score": 85, "reasoning": "Good example.", "issues": []}',
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->once()->andReturn($fakeResult);

    $service = new DatasetEvaluationService($mockInference, new EvaluationPromptBuilderService());
    $result = $service->evaluate(['text' => 'hello'], null, $project);

    expect($result)->toBeInstanceOf(EvaluationResultDTO::class)
        ->and($result->score)->toBe(85)
        ->and($result->reasoning)->toBe('Good example.')
        ->and($result->issues)->toBe([]);
});

test('DatasetEvaluationService strips markdown fences from response', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'evaluation_enabled' => true,
    ]);

    $fakeResult = new GenerationResultDTO(
        rows: [],
        promptTokens: 5,
        completionTokens: 10,
        totalTokens: 15,
        latencyMs: 50,
        rawContent: "```json\n{\"score\": 90, \"reasoning\": \"Excellent.\", \"issues\": []}\n```",
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->once()->andReturn($fakeResult);

    $service = new DatasetEvaluationService($mockInference, new EvaluationPromptBuilderService());
    $result = $service->evaluate(['text' => 'test'], null, $project);

    expect($result->score)->toBe(90);
});

test('DatasetEvaluationService returns parse_error result on invalid JSON', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'evaluation_enabled' => true,
    ]);

    $fakeResult = new GenerationResultDTO(
        rows: [],
        promptTokens: 5,
        completionTokens: 5,
        totalTokens: 10,
        latencyMs: 50,
        rawContent: 'not valid json at all',
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->once()->andReturn($fakeResult);

    $service = new DatasetEvaluationService($mockInference, new EvaluationPromptBuilderService());
    $result = $service->evaluate(['text' => 'test'], null, $project);

    expect($result->score)->toBe(0)
        ->and($result->issues)->toContain('parse_error');
});

// --- DatasetEvaluationService: disabled mode ---

test('DatasetEvaluationService returns null when evaluation is disabled', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'evaluation_enabled' => false,
    ]);

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldNotReceive('execute');

    $service = new DatasetEvaluationService($mockInference, new EvaluationPromptBuilderService());
    $result = $service->evaluate(['text' => 'hello'], null, $project);

    expect($result)->toBeNull();
});

// --- DatasetEvaluationService: threshold ---

test('DatasetEvaluationService passes() returns true when score meets threshold', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'minimum_quality_score' => 75,
    ]);

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $service = new DatasetEvaluationService($mockInference, new EvaluationPromptBuilderService());

    $passing = new EvaluationResultDTO(score: 75, reasoning: 'OK', issues: []);
    $failing = new EvaluationResultDTO(score: 74, reasoning: 'Low', issues: ['too_generic']);

    expect($service->passes($passing, $project))->toBeTrue()
        ->and($service->passes($failing, $project))->toBeFalse();
});

// --- Integration: evaluation rejects rows below threshold ---

test('GenerateDatasetBatchJob rejects rows below minimum quality score', function () {
    config(['queue.default' => 'sync']);

    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'record_count' => 2,
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'Generate test data.',
        'evaluation_enabled' => true,
        'minimum_quality_score' => 80,
        'replacement_generation_enabled' => false,
    ]);

    $fakeGenResult = new GenerationResultDTO(
        rows: [['text' => 'row one'], ['text' => 'row two']],
        promptTokens: 10,
        completionTokens: 20,
        totalTokens: 30,
        latencyMs: 100,
    );

    $fakeEvalPass = new GenerationResultDTO(
        rows: [],
        promptTokens: 5,
        completionTokens: 10,
        totalTokens: 15,
        latencyMs: 50,
        rawContent: '{"score": 90, "reasoning": "Great.", "issues": []}',
    );
    $fakeEvalFail = new GenerationResultDTO(
        rows: [],
        promptTokens: 5,
        completionTokens: 10,
        totalTokens: 15,
        latencyMs: 50,
        rawContent: '{"score": 50, "reasoning": "Too generic.", "issues": ["too_generic"]}',
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->times(3)->andReturn($fakeGenResult, $fakeEvalPass, $fakeEvalFail);
    app()->instance(InferenceExecutionService::class, $mockInference);

    Event::fake();

    $version = app(StartDatasetGenerationAction::class)->execute($project, 2);
    $version->refresh();

    $rows = DatasetRow::where('dataset_version_id', $version->id)->where('is_duplicate', false)->get();

    $passed = $rows->where('evaluation_failed', false);
    $rejected = $rows->where('evaluation_failed', true);

    expect($passed)->toHaveCount(1)
        ->and($rejected)->toHaveCount(1)
        ->and($rejected->first()->quality_score)->toBe(50)
        ->and($rejected->first()->quality_issues)->toContain('too_generic');
});

// --- Integration: evaluation disabled — no evaluation calls ---

test('GenerateDatasetBatchJob skips evaluation when disabled', function () {
    config(['queue.default' => 'sync']);

    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'record_count' => 2,
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'Generate test data.',
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
        ->and($rows->every(fn ($r) => $r->quality_score === null))->toBeTrue()
        ->and($rows->every(fn ($r) => $r->evaluation_failed === false))->toBeTrue();
});

// --- Integration: replacement triggered by evaluation rejections ---

test('GenerateDatasetBatchJob triggers replacement generation for evaluation-rejected rows', function () {
    config(['queue.default' => 'sync']);

    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'record_count' => 2,
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'Generate test data.',
        'evaluation_enabled' => true,
        'minimum_quality_score' => 80,
        'replacement_generation_enabled' => true,
    ]);

    $fakeGenResult = new GenerationResultDTO(
        rows: [['text' => 'bad row one'], ['text' => 'bad row two']],
        promptTokens: 10,
        completionTokens: 20,
        totalTokens: 30,
        latencyMs: 100,
    );

    $fakeReplacement = new GenerationResultDTO(
        rows: [['text' => 'good replacement one'], ['text' => 'good replacement two']],
        promptTokens: 10,
        completionTokens: 20,
        totalTokens: 30,
        latencyMs: 100,
    );

    $evalFail = new GenerationResultDTO(
        rows: [], promptTokens: 5, completionTokens: 5, totalTokens: 10, latencyMs: 30,
        rawContent: '{"score": 40, "reasoning": "Poor.", "issues": ["too_generic"]}',
    );
    $evalPass = new GenerationResultDTO(
        rows: [], promptTokens: 5, completionTokens: 5, totalTokens: 10, latencyMs: 30,
        rawContent: '{"score": 95, "reasoning": "Excellent.", "issues": []}',
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')
        ->andReturn(
            $fakeGenResult,   // initial generation
            $evalFail,        // eval row 1 (fail)
            $evalFail,        // eval row 2 (fail)
            $fakeReplacement, // replacement generation
            $evalPass,        // eval replacement row 1 (pass)
            $evalPass,        // eval replacement row 2 (pass)
        );
    app()->instance(InferenceExecutionService::class, $mockInference);

    Event::fake();

    $version = app(StartDatasetGenerationAction::class)->execute($project, 2);
    $version->refresh();

    $acceptedRows = DatasetRow::where('dataset_version_id', $version->id)
        ->where('is_duplicate', false)
        ->where('evaluation_failed', false)
        ->get();

    expect($acceptedRows)->toHaveCount(2)
        ->and($acceptedRows->first()->payload['text'])->toContain('good replacement');
});
