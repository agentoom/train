<?php

use App\Actions\Datasets\UpdateDatasetProjectAction;
use App\DTOs\GenerationResultDTO;
use App\DTOs\PromptConfigDTO;
use App\Enums\BatchStatus;
use App\Jobs\GenerateDatasetBatchJob;
use App\Models\AIProvider;
use App\Models\DatasetProject;
use App\Models\DatasetRow;
use App\Models\DatasetSource;
use App\Models\DatasetVersion;
use App\Models\GenerationBatch;
use App\Services\AI\InferenceExecutionService;
use App\Services\Dataset\AugmentationPromptBuilderService;
use App\Services\Dataset\ConversationPromptBuilderService;
use App\Services\Dataset\DatasetProgressService;
use App\Services\Dataset\DatasetSourceParsingService;
use App\Services\Dataset\DatasetValidationService;
use App\Services\Dataset\HashDeduplicationService;
use App\Services\Dataset\NegativeExampleService;
use App\Services\Dataset\PromptBuilderService;
use App\Services\Dataset\SemanticDeduplicationService;
use App\Services\Evaluation\DatasetEvaluationService;
use App\Services\Pipeline\CriticService;
use App\Services\Pipeline\RefinerService;
use App\Support\Cost\CostEstimator;
use App\Support\Json\JsonRepairer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// ─── DatasetSourceParsingService ─────────────────────────────────────────────

test('parses JSONL content into rows', function () {
    $service = app(DatasetSourceParsingService::class);
    $content = '{"user":"Hello","assistant":"Hi"}'."\n".'{"user":"Bye","assistant":"Goodbye"}';
    $file = UploadedFile::fake()->createWithContent('data.jsonl', $content);

    $result = $service->parse($file);

    expect($result['rows'])->toHaveCount(2)
        ->and($result['rows'][0]['user'])->toBe('Hello')
        ->and($result['rows'][1]['assistant'])->toBe('Goodbye');
});

test('parses JSON array content into rows', function () {
    $service = app(DatasetSourceParsingService::class);
    $content = json_encode([['q' => 'What?', 'a' => 'This.'], ['q' => 'Why?', 'a' => 'Because.']]);
    $file = UploadedFile::fake()->createWithContent('data.json', $content);

    $result = $service->parse($file);

    expect($result['rows'])->toHaveCount(2)
        ->and($result['rows'][0]['q'])->toBe('What?');
});

test('parses CSV content into rows with headers', function () {
    $service = app(DatasetSourceParsingService::class);
    $content = "name,age\nAlice,30\nBob,25";
    $file = UploadedFile::fake()->createWithContent('data.csv', $content);

    $result = $service->parse($file);

    expect($result['rows'])->toHaveCount(2)
        ->and($result['rows'][0]['name'])->toBe('Alice')
        ->and($result['rows'][1]['age'])->toBe('25');
});

test('parses TXT content into rows with text key', function () {
    $service = app(DatasetSourceParsingService::class);
    $content = "Line one\nLine two\nLine three";
    $file = UploadedFile::fake()->createWithContent('data.txt', $content);

    $result = $service->parse($file);

    expect($result['rows'])->toHaveCount(3)
        ->and($result['rows'][0])->toBe(['text' => 'Line one']);
});

test('infers schema from parsed rows', function () {
    $service = app(DatasetSourceParsingService::class);
    $content = '{"user":"Hello","score":5}'."\n".'{"user":"Bye","score":3}';
    $file = UploadedFile::fake()->createWithContent('data.jsonl', $content);

    $result = $service->parse($file);

    expect($result['schema'])->toHaveKey('type')
        ->and($result['schema']['type'])->toBe('object')
        ->and($result['schema']['properties'])->toHaveKey('user')
        ->and($result['schema']['properties'])->toHaveKey('score');
});

test('builds metadata with field distribution', function () {
    $service = app(DatasetSourceParsingService::class);
    $content = '{"user":"Hello"}'."\n".'{"user":"Bye"}';
    $file = UploadedFile::fake()->createWithContent('data.jsonl', $content);

    $result = $service->parse($file);

    expect($result['metadata'])->toHaveKey('field_distribution')
        ->and($result['metadata']['field_distribution']['user'])->toBe(1.0)
        ->and($result['metadata']['row_count'])->toBe(2);
});

test('sampleRows rotates by batch index', function () {
    $service = app(DatasetSourceParsingService::class);
    $rows = [['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4]];

    $batch0 = $service->sampleRows($rows, 2, 0);
    $batch1 = $service->sampleRows($rows, 2, 1);

    expect($batch0[0]['id'])->toBe(1)
        ->and($batch1[0]['id'])->toBe(3);
});

test('loadRows returns empty array for missing file', function () {
    $service = app(DatasetSourceParsingService::class);

    $rows = $service->loadRows('nonexistent/path.jsonl', 'jsonl');

    expect($rows)->toBe([]);
});

test('loadRows reads stored JSONL file', function () {
    Storage::fake('local');
    $service = app(DatasetSourceParsingService::class);

    $content = '{"text":"row1"}'."\n".'{"text":"row2"}';
    Storage::disk('local')->put('dataset-sources/test.jsonl', $content);

    $rows = $service->loadRows('dataset-sources/test.jsonl', 'jsonl');

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['text'])->toBe('row1');
});

// ─── AugmentationPromptBuilderService ────────────────────────────────────────

test('AugmentationPromptBuilderService builds prompt with source examples', function () {
    $builder = app(AugmentationPromptBuilderService::class);
    $project = DatasetProject::factory()->create([
        'augmentation_enabled' => true,
        'augmentation_mode' => 'similar',
        'augmentation_strength' => 0.5,
    ]);

    $sourceRows = [['user' => 'My package never arrived.', 'intent' => 'complaint']];

    $prompt = $builder->build($project, $sourceRows, batchIndex: 0, recordCount: 5);

    expect($prompt->userPrompt)->toContain('SOURCE EXAMPLES')
        ->and($prompt->userPrompt)->toContain('LEAKAGE PREVENTION')
        ->and($prompt->userPrompt)->toContain('Generate EXACTLY 5');
});

test('AugmentationPromptBuilderService uses mode-specific system prompt', function () {
    $builder = app(AugmentationPromptBuilderService::class);
    $project = DatasetProject::factory()->create(['augmentation_mode' => 'adversarial']);

    $prompt = $builder->build($project, [], batchIndex: 0, recordCount: 2);

    expect($prompt->systemPrompt)->toContain('adversarial');
});

test('AugmentationPromptBuilderService maps strength to temperature', function () {
    $builder = app(AugmentationPromptBuilderService::class);

    $lowProject = DatasetProject::factory()->create(['augmentation_strength' => 0.1]);
    $highProject = DatasetProject::factory()->create(['augmentation_strength' => 1.0]);

    $lowPrompt = $builder->build($lowProject, [], batchIndex: 0, recordCount: 2);
    $highPrompt = $builder->build($highProject, [], batchIndex: 0, recordCount: 2);

    expect($lowPrompt->temperature)->toBeLessThan($highPrompt->temperature);
});

test('AugmentationPromptBuilderService includes fewer examples at high strength', function () {
    $builder = app(AugmentationPromptBuilderService::class);
    $rows = array_fill(0, 20, ['text' => 'example']);

    $lowProject = DatasetProject::factory()->create(['augmentation_strength' => 0.1]);
    $highProject = DatasetProject::factory()->create(['augmentation_strength' => 1.0]);

    $lowPrompt = $builder->build($lowProject, $rows, batchIndex: 0, recordCount: 2);
    $highPrompt = $builder->build($highProject, $rows, batchIndex: 0, recordCount: 2);

    $lowCount = substr_count($lowPrompt->userPrompt, '"text"');
    $highCount = substr_count($highPrompt->userPrompt, '"text"');

    expect($lowCount)->toBeGreaterThan($highCount);
});

// ─── DatasetSource model ──────────────────────────────────────────────────────

test('DatasetSource belongs to DatasetProject', function () {
    $project = DatasetProject::factory()->create();
    $source = DatasetSource::factory()->create(['dataset_project_id' => $project->id]);

    expect($source->datasetProject->id)->toBe($project->id);
});

test('DatasetProject has many sources', function () {
    $project = DatasetProject::factory()->create();
    DatasetSource::factory()->count(3)->create(['dataset_project_id' => $project->id]);

    expect($project->sources()->count())->toBe(3);
});

// ─── Augmentation mode in GenerateDatasetBatchJob ────────────────────────────

test('GenerateDatasetBatchJob uses augmentation prompt when augmentation_enabled', function () {
    $provider = AIProvider::factory()->create();
    $project = DatasetProject::factory()->create([
        'ai_provider_id' => $provider->id,
        'augmentation_enabled' => true,
        'augmentation_mode' => 'similar',
        'augmentation_strength' => 0.5,
    ]);
    $version = DatasetVersion::factory()->create(['dataset_project_id' => $project->id]);
    $batch = GenerationBatch::factory()->create([
        'dataset_version_id' => $version->id,
        'batch_number' => 1,
        'offset' => 0,
        'limit' => 2,
        'status' => BatchStatus::Pending,
    ]);

    $fakeResult = new GenerationResultDTO(
        rows: [['text' => 'augmented row 1'], ['text' => 'augmented row 2']],
        promptTokens: 100,
        completionTokens: 200,
        totalTokens: 300,
        latencyMs: 500,
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->once()->andReturn($fakeResult);
    app()->instance(InferenceExecutionService::class, $mockInference);

    $mockAugBuilder = Mockery::mock(AugmentationPromptBuilderService::class);
    $mockAugBuilder->shouldReceive('buildFromVersion')->once()->andReturn(
        new PromptConfigDTO(
            systemPrompt: 'Augmentation system prompt',
            userPrompt: 'Generate 2 augmented rows.',
            model: 'gpt-4o-mini',
            temperature: 0.7,
            maxTokens: 2048,
            schema: null,
            jsonMode: false,
        )
    );
    app()->instance(AugmentationPromptBuilderService::class, $mockAugBuilder);

    (new GenerateDatasetBatchJob($batch->id))->handle(
        app(InferenceExecutionService::class),
        app(PromptBuilderService::class),
        app(ConversationPromptBuilderService::class),
        app(DatasetValidationService::class),
        app(DatasetProgressService::class),
        app(JsonRepairer::class),
        app(CostEstimator::class),
        app(HashDeduplicationService::class),
        app(SemanticDeduplicationService::class),
        app(DatasetEvaluationService::class),
        app(CriticService::class),
        app(RefinerService::class),
        app(NegativeExampleService::class),
        app(AugmentationPromptBuilderService::class),
        app(DatasetSourceParsingService::class),
    );

    expect(DatasetRow::where('generation_batch_id', $batch->id)->where('is_duplicate', false)->count())->toBe(2);
    $batch->refresh();
    expect($batch->status)->toBe(BatchStatus::Completed);
});

test('GenerateDatasetBatchJob falls back to standard prompt when augmentation disabled', function () {
    $provider = AIProvider::factory()->create();
    $project = DatasetProject::factory()->create([
        'ai_provider_id' => $provider->id,
        'augmentation_enabled' => false,
    ]);
    $version = DatasetVersion::factory()->create(['dataset_project_id' => $project->id]);
    $batch = GenerationBatch::factory()->create([
        'dataset_version_id' => $version->id,
        'batch_number' => 1,
        'offset' => 0,
        'limit' => 2,
        'status' => BatchStatus::Pending,
    ]);

    $fakeResult = new GenerationResultDTO(
        rows: [['text' => 'row1'], ['text' => 'row2']],
        promptTokens: 50,
        completionTokens: 100,
        totalTokens: 150,
        latencyMs: 300,
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->once()->andReturn($fakeResult);
    app()->instance(InferenceExecutionService::class, $mockInference);

    $mockAugBuilder = Mockery::mock(AugmentationPromptBuilderService::class);
    $mockAugBuilder->shouldNotReceive('buildFromVersion');
    app()->instance(AugmentationPromptBuilderService::class, $mockAugBuilder);

    (new GenerateDatasetBatchJob($batch->id))->handle(
        app(InferenceExecutionService::class),
        app(PromptBuilderService::class),
        app(ConversationPromptBuilderService::class),
        app(DatasetValidationService::class),
        app(DatasetProgressService::class),
        app(JsonRepairer::class),
        app(CostEstimator::class),
        app(HashDeduplicationService::class),
        app(SemanticDeduplicationService::class),
        app(DatasetEvaluationService::class),
        app(CriticService::class),
        app(RefinerService::class),
        app(NegativeExampleService::class),
        app(AugmentationPromptBuilderService::class),
        app(DatasetSourceParsingService::class),
    );

    expect(DatasetRow::where('generation_batch_id', $batch->id)->where('is_duplicate', false)->count())->toBe(2);
});

// ─── Augmentation settings persistence ───────────────────────────────────────

test('augmentation fields are persisted on DatasetProject', function () {
    $project = DatasetProject::factory()->create([
        'augmentation_enabled' => true,
        'augmentation_mode' => 'edge_cases',
        'augmentation_strength' => 0.8,
        'augmentation_target_count' => 5000,
        'augmentation_expand_percent' => 300,
    ]);

    $project->refresh();

    expect($project->augmentation_enabled)->toBeTrue()
        ->and($project->augmentation_mode)->toBe('edge_cases')
        ->and($project->augmentation_strength)->toBe(0.8)
        ->and($project->augmentation_target_count)->toBe(5000)
        ->and($project->augmentation_expand_percent)->toBe(300);
});

test('UpdateDatasetProjectAction persists augmentation fields', function () {
    $project = DatasetProject::factory()->create();

    app(UpdateDatasetProjectAction::class)->execute($project, [
        'augmentation_enabled' => true,
        'augmentation_mode' => 'diverse',
        'augmentation_strength' => 0.7,
        'augmentation_target_count' => 1000,
        'augmentation_expand_percent' => null,
    ]);

    $project->refresh();

    expect($project->augmentation_enabled)->toBeTrue()
        ->and($project->augmentation_mode)->toBe('diverse')
        ->and($project->augmentation_strength)->toBe(0.7)
        ->and($project->augmentation_target_count)->toBe(1000);
});
