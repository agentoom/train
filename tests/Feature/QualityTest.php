<?php

use App\DTOs\GenerationResultDTO;
use App\Enums\BatchStatus;
use App\Enums\DatasetStatus;
use App\Jobs\GenerateDatasetBatchJob;
use App\Livewire\Datasets\DatasetDetail;
use App\Livewire\Datasets\DatasetForm;
use App\Models\AIProvider;
use App\Models\DatasetProject;
use App\Models\DatasetRow;
use App\Models\DatasetVersion;
use App\Models\GenerationBatch;
use App\Models\User;
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
use Livewire\Livewire;

// --- PromptBuilderService ---

test('PromptBuilderService includes exact record count in prompt', function () {
    $project = DatasetProject::factory()->create(['record_count' => 25]);
    $service = app(PromptBuilderService::class);

    $dto = $service->build($project, 0, 25);

    expect($dto->userPrompt)->toContain('EXACTLY 25');
});

test('PromptBuilderService includes batch awareness in prompt', function () {
    $project = DatasetProject::factory()->create();
    $service = app(PromptBuilderService::class);

    $dto = $service->build($project, 2, 10, 5);

    expect($dto->userPrompt)->toContain('batch 3 of 5');
});

test('PromptBuilderService includes uniqueness instructions', function () {
    $project = DatasetProject::factory()->create(['uniqueness_level' => 'aggressive']);
    $service = app(PromptBuilderService::class);

    $dto = $service->build($project, 0, 10);

    expect($dto->userPrompt)->toContain('UNIQUENESS REQUIREMENTS')
        ->and($dto->userPrompt)->toContain('Aggressive');
});

test('PromptBuilderService includes conservative uniqueness level', function () {
    $project = DatasetProject::factory()->create(['uniqueness_level' => 'conservative']);
    $service = app(PromptBuilderService::class);

    $dto = $service->build($project, 0, 10);

    expect($dto->userPrompt)->toContain('Conservative');
});

test('PromptBuilderService includes diversity dimensions in prompt', function () {
    $project = DatasetProject::factory()->create([
        'diversity_dimensions' => ['topic' => ['shipping', 'returns'], 'tone' => ['friendly', 'angry']],
    ]);
    $service = app(PromptBuilderService::class);

    $dto = $service->build($project, 0, 10, 3);

    expect($dto->userPrompt)->toContain('DIVERSITY REQUIREMENTS')
        ->and($dto->userPrompt)->toContain('topic')
        ->and($dto->userPrompt)->toContain('tone');
});

test('PromptBuilderService rotates diversity dimensions across batches', function () {
    $project = DatasetProject::factory()->create([
        'diversity_dimensions' => ['topic' => ['shipping', 'returns', 'payment']],
    ]);
    $service = app(PromptBuilderService::class);

    $dto0 = $service->build($project, 0, 5, 3);
    $dto1 = $service->build($project, 1, 5, 3);
    $dto2 = $service->build($project, 2, 5, 3);

    expect($dto0->userPrompt)->toContain('"shipping"');
    expect($dto1->userPrompt)->toContain('"returns"');
    expect($dto2->userPrompt)->toContain('"payment"');
});

test('PromptBuilderService injects seed context when generation_seed is set', function () {
    $project = DatasetProject::factory()->create(['generation_seed' => 'my-seed-123']);
    $service = app(PromptBuilderService::class);

    $dto = $service->build($project, 0, 10);

    expect($dto->userPrompt)->toContain('Reproducibility seed context');
});

test('PromptBuilderService derives different seeds per batch', function () {
    $project = DatasetProject::factory()->create(['generation_seed' => 'fixed-seed']);
    $service = app(PromptBuilderService::class);

    $dto0 = $service->build($project, 0, 5);
    $dto1 = $service->build($project, 1, 5);

    expect($dto0->userPrompt)->toContain('Reproducibility seed context');
    expect($dto1->userPrompt)->toContain('Reproducibility seed context');
    expect($dto0->userPrompt)->not->toBe($dto1->userPrompt);
});

test('PromptBuilderService buildFromVersion uses project diversity settings', function () {
    $project = DatasetProject::factory()->create([
        'diversity_dimensions' => ['tone' => ['formal', 'casual']],
        'uniqueness_level' => 'aggressive',
    ]);
    $version = DatasetVersion::factory()->create(['dataset_project_id' => $project->id]);
    $service = app(PromptBuilderService::class);

    $dto = $service->buildFromVersion($version, 0, 10, 2);

    expect($dto->userPrompt)->toContain('DIVERSITY REQUIREMENTS')
        ->and($dto->userPrompt)->toContain('Aggressive');
});

// --- HashDeduplicationService ---

test('HashDeduplicationService generates consistent hash for same payload', function () {
    $service = app(HashDeduplicationService::class);
    $payload = ['name' => 'John', 'age' => 30];

    expect($service->hash($payload))->toBe($service->hash($payload));
});

test('HashDeduplicationService normalizes case and whitespace', function () {
    $service = app(HashDeduplicationService::class);

    $hash1 = $service->hash(['text' => 'Hello World']);
    $hash2 = $service->hash(['text' => '  hello   world  ']);

    expect($hash1)->toBe($hash2);
});

test('HashDeduplicationService normalizes key ordering', function () {
    $service = app(HashDeduplicationService::class);

    $hash1 = $service->hash(['a' => 'foo', 'b' => 'bar']);
    $hash2 = $service->hash(['b' => 'bar', 'a' => 'foo']);

    expect($hash1)->toBe($hash2);
});

test('HashDeduplicationService detects duplicate in same version', function () {
    $version = DatasetVersion::factory()->create();
    $batch = GenerationBatch::factory()->create(['dataset_version_id' => $version->id]);
    $service = app(HashDeduplicationService::class);

    $payload = ['text' => 'unique content here'];
    $hash = $service->hash($payload);

    DatasetRow::create([
        'dataset_version_id' => $version->id,
        'generation_batch_id' => $batch->id,
        'row_index' => 0,
        'payload' => $payload,
        'is_valid' => true,
        'content_hash' => $hash,
        'is_duplicate' => false,
    ]);

    expect($service->isDuplicate($version->id, $hash))->toBeTrue();
});

test('HashDeduplicationService does not flag duplicate across different versions', function () {
    $version1 = DatasetVersion::factory()->create();
    $version2 = DatasetVersion::factory()->create();
    $batch1 = GenerationBatch::factory()->create(['dataset_version_id' => $version1->id]);
    $service = app(HashDeduplicationService::class);

    $payload = ['text' => 'shared content'];
    $hash = $service->hash($payload);

    DatasetRow::create([
        'dataset_version_id' => $version1->id,
        'generation_batch_id' => $batch1->id,
        'row_index' => 0,
        'payload' => $payload,
        'is_valid' => true,
        'content_hash' => $hash,
        'is_duplicate' => false,
    ]);

    expect($service->isDuplicate($version2->id, $hash))->toBeFalse();
});

test('HashDeduplicationService does not flag existing duplicate rows as blockers', function () {
    $version = DatasetVersion::factory()->create();
    $batch = GenerationBatch::factory()->create(['dataset_version_id' => $version->id]);
    $service = app(HashDeduplicationService::class);

    $payload = ['text' => 'some content'];
    $hash = $service->hash($payload);

    DatasetRow::create([
        'dataset_version_id' => $version->id,
        'generation_batch_id' => $batch->id,
        'row_index' => 0,
        'payload' => $payload,
        'is_valid' => false,
        'content_hash' => $hash,
        'is_duplicate' => true,
    ]);

    expect($service->isDuplicate($version->id, $hash))->toBeFalse();
});

// --- SemanticDeduplicationService ---

test('SemanticDeduplicationService skips deduplication when Typesense is not configured', function () {
    // In tests SCOUT_DRIVER=collection — semantic dedup must return false (not Jaccard)
    $version = DatasetVersion::factory()->create();
    $service = app(SemanticDeduplicationService::class);

    $candidate = ['text' => 'the customer wants to return a product they purchased'];

    expect($service->isSemanticallyDuplicate($version->id, $candidate, 0.5))->toBeFalse();
});

test('SemanticDeduplicationService returns false for any candidate when not using Typesense', function () {
    $version = DatasetVersion::factory()->create();
    $service = app(SemanticDeduplicationService::class);

    $candidate = ['text' => 'invoice payment processing error in checkout'];

    expect($service->isSemanticallyDuplicate($version->id, $candidate, 0.92))->toBeFalse();
});

test('SemanticDeduplicationService similarityScore returns 0.0 when Typesense is not configured', function () {
    $service = app(SemanticDeduplicationService::class);

    $score = $service->similarityScore(
        ['text' => 'hello world foo bar'],
        ['text' => 'hello world baz qux'],
    );

    expect($score)->toBe(0.0);
});

// --- Hash deduplication in job ---

test('GenerateDatasetBatchJob marks duplicate rows and tracks count', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'semantic_deduplication_enabled' => false,
        'replacement_generation_enabled' => false,
    ]);
    $version = DatasetVersion::factory()->create([
        'dataset_project_id' => $project->id,
        'status' => DatasetStatus::Queued,
    ]);
    $batch = GenerationBatch::factory()->create([
        'dataset_version_id' => $version->id,
        'batch_number' => 1,
        'offset' => 0,
        'limit' => 3,
        'status' => BatchStatus::Pending,
    ]);

    $hashService = app(HashDeduplicationService::class);
    $existingPayload = ['text' => 'row1'];
    $previousBatch = GenerationBatch::factory()->completed()->create(['dataset_version_id' => $version->id]);
    DatasetRow::create([
        'dataset_version_id' => $version->id,
        'generation_batch_id' => $previousBatch->id,
        'row_index' => 99,
        'payload' => $existingPayload,
        'is_valid' => true,
        'content_hash' => $hashService->hash($existingPayload),
        'is_duplicate' => false,
    ]);

    $fakeResult = new GenerationResultDTO(
        rows: [['text' => 'row1'], ['text' => 'row2'], ['text' => 'row3']],
        promptTokens: 10,
        completionTokens: 20,
        totalTokens: 30,
        latencyMs: 100,
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->once()->andReturn($fakeResult);
    app()->instance(InferenceExecutionService::class, $mockInference);

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

    $freshBatch = $batch->fresh();
    expect($freshBatch->duplicates_detected)->toBe(1);
    expect(DatasetRow::where('generation_batch_id', $batch->id)->where('is_duplicate', true)->count())->toBe(1);
    expect(DatasetRow::where('generation_batch_id', $batch->id)->where('is_duplicate', false)->count())->toBe(2);
});

// --- Diversity fields on DatasetProject ---

test('DatasetProject stores and retrieves diversity_dimensions as array', function () {
    $dims = ['topic' => ['a', 'b'], 'tone' => ['friendly']];
    $project = DatasetProject::factory()->create(['diversity_dimensions' => $dims]);

    expect($project->fresh()->diversity_dimensions)->toBe($dims);
});

test('DatasetProject stores uniqueness_level and dedup settings', function () {
    $project = DatasetProject::factory()->create([
        'uniqueness_level' => 'aggressive',
        'semantic_deduplication_enabled' => false,
        'semantic_similarity_threshold' => 0.85,
        'replacement_generation_enabled' => false,
    ]);

    $fresh = $project->fresh();
    expect($fresh->uniqueness_level)->toBe('aggressive')
        ->and($fresh->semantic_deduplication_enabled)->toBeFalse()
        ->and((float) $fresh->semantic_similarity_threshold)->toBe(0.85)
        ->and($fresh->replacement_generation_enabled)->toBeFalse();
});

// --- DatasetForm with new fields ---

test('DatasetForm saves diversity fields on create', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(DatasetForm::class)
        ->set('name', 'Diversity Test Dataset')
        ->set('recordCount', 10)
        ->set('uniquenessLevel', 'aggressive')
        ->set('semanticDeduplicationEnabled', false)
        ->set('semanticSimilarityThreshold', 0.85)
        ->set('replacementGenerationEnabled', false)
        ->set('generationSeed', 'test-seed-42')
        ->call('save');

    $project = DatasetProject::where('user_id', $user->id)->where('name', 'Diversity Test Dataset')->first();
    expect($project)->not->toBeNull()
        ->and($project->uniqueness_level)->toBe('aggressive')
        ->and($project->semantic_deduplication_enabled)->toBeFalse()
        ->and((float) $project->semantic_similarity_threshold)->toBe(0.85)
        ->and($project->replacement_generation_enabled)->toBeFalse()
        ->and($project->generation_seed)->toBe('test-seed-42');
});

test('DatasetForm validates uniqueness_level must be valid option', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(DatasetForm::class)
        ->set('name', 'Test')
        ->set('recordCount', 10)
        ->set('uniquenessLevel', 'invalid_value')
        ->call('save')
        ->assertHasErrors(['uniquenessLevel']);
});

test('DatasetForm validates semantic_similarity_threshold range', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(DatasetForm::class)
        ->set('name', 'Test')
        ->set('recordCount', 10)
        ->set('semanticSimilarityThreshold', 0.5)
        ->call('save')
        ->assertHasErrors(['semanticSimilarityThreshold']);
});

// --- Quality metrics in DatasetDetail ---

test('DatasetDetail exposes quality metrics when rows exist', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => $user->id, 'status' => DatasetStatus::Completed]);
    $version = DatasetVersion::factory()->create(['dataset_project_id' => $project->id, 'status' => DatasetStatus::Completed]);
    $batch = GenerationBatch::factory()->completed()->create(['dataset_version_id' => $version->id]);

    DatasetRow::create([
        'dataset_version_id' => $version->id,
        'generation_batch_id' => $batch->id,
        'row_index' => 0,
        'payload' => ['text' => 'unique row'],
        'is_valid' => true,
        'content_hash' => 'hashunique1',
        'is_duplicate' => false,
    ]);
    DatasetRow::create([
        'dataset_version_id' => $version->id,
        'generation_batch_id' => $batch->id,
        'row_index' => 1,
        'payload' => ['text' => 'duplicate row'],
        'is_valid' => false,
        'content_hash' => 'hashunique1',
        'is_duplicate' => true,
    ]);

    Livewire::actingAs($user)
        ->test(DatasetDetail::class, ['project' => $project])
        ->assertSee('Quality Metrics')
        ->assertSee('Uniqueness');
});
