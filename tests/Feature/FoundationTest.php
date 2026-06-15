<?php

use App\Enums\AIProviderType;
use App\Enums\BatchStatus;
use App\Enums\DatasetStatus;
use App\Enums\ExportFormat;
use App\Models\AIProvider;
use App\Models\DatasetProject;
use App\Models\DatasetRow;
use App\Models\DatasetVersion;
use App\Models\GenerationBatch;
use App\Models\GenerationUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// --- Enums ---

test('AIProviderType enum has correct values', function () {
    expect(AIProviderType::OpenAI->value)->toBe('openai')
        ->and(AIProviderType::Anthropic->value)->toBe('anthropic')
        ->and(AIProviderType::OpenRouter->value)->toBe('openrouter')
        ->and(AIProviderType::Generic->value)->toBe('generic');
});

test('BatchStatus enum has correct values', function () {
    expect(BatchStatus::Pending->value)->toBe('pending')
        ->and(BatchStatus::Running->value)->toBe('running')
        ->and(BatchStatus::Completed->value)->toBe('completed')
        ->and(BatchStatus::Failed->value)->toBe('failed')
        ->and(BatchStatus::Cancelled->value)->toBe('cancelled');
});

test('DatasetStatus enum has correct values', function () {
    expect(DatasetStatus::Draft->value)->toBe('draft')
        ->and(DatasetStatus::Queued->value)->toBe('queued')
        ->and(DatasetStatus::Running->value)->toBe('running')
        ->and(DatasetStatus::Completed->value)->toBe('completed')
        ->and(DatasetStatus::Failed->value)->toBe('failed')
        ->and(DatasetStatus::Cancelled->value)->toBe('cancelled');
});

test('ExportFormat enum has correct values and helpers', function () {
    expect(ExportFormat::Json->value)->toBe('json')
        ->and(ExportFormat::Jsonl->value)->toBe('jsonl')
        ->and(ExportFormat::Csv->value)->toBe('csv')
        ->and(ExportFormat::Json->mimeType())->toBe('application/json')
        ->and(ExportFormat::Csv->mimeType())->toBe('text/csv')
        ->and(ExportFormat::Json->extension())->toBe('json');
});

// --- Migrations & Models ---

test('AIProvider model can be created and type is cast to enum', function () {
    $user = User::factory()->create();

    $provider = AIProvider::create([
        'user_id' => $user->id,
        'label' => 'Test OpenAI',
        'type' => AIProviderType::OpenAI,
        'api_key' => 'sk-test-key',
        'default_model' => 'gpt-4o',
        'is_enabled' => true,
    ]);

    expect($provider->type)->toBe(AIProviderType::OpenAI)
        ->and($provider->is_enabled)->toBeTrue();

    $this->assertModelExists($provider);
});

test('AIProvider api_key is encrypted at rest', function () {
    $user = User::factory()->create();

    $provider = AIProvider::create([
        'user_id' => $user->id,
        'label' => 'Encrypted Key Test',
        'type' => AIProviderType::OpenAI,
        'api_key' => 'sk-secret-key',
        'default_model' => 'gpt-4o',
    ]);

    $raw = DB::table('ai_providers')->where('id', $provider->id)->value('api_key');

    expect($raw)->not->toBe('sk-secret-key')
        ->and($provider->fresh()->api_key)->toBe('sk-secret-key');
});

test('DatasetProject model can be created with status enum cast', function () {
    $user = User::factory()->create();

    $project = DatasetProject::create([
        'user_id' => $user->id,
        'name' => 'Test Dataset',
        'record_count' => 100,
        'status' => DatasetStatus::Draft,
    ]);

    expect($project->status)->toBe(DatasetStatus::Draft)
        ->and($project->record_count)->toBe(100);

    $this->assertModelExists($project);
});

test('DatasetVersion model can be created', function () {
    $user = User::factory()->create();
    $project = DatasetProject::create([
        'user_id' => $user->id,
        'name' => 'Test Project',
        'record_count' => 10,
        'status' => DatasetStatus::Draft,
    ]);

    $version = DatasetVersion::create([
        'dataset_project_id' => $project->id,
        'version_number' => 1,
        'record_count' => 10,
        'status' => DatasetStatus::Draft,
    ]);

    expect($version->version_number)->toBe(1)
        ->and($version->status)->toBe(DatasetStatus::Draft);

    $this->assertModelExists($version);
});

test('GenerationBatch model can be created with status enum cast', function () {
    $user = User::factory()->create();
    $project = DatasetProject::create([
        'user_id' => $user->id,
        'name' => 'Test Project',
        'record_count' => 10,
        'status' => DatasetStatus::Draft,
    ]);
    $version = DatasetVersion::create([
        'dataset_project_id' => $project->id,
        'version_number' => 1,
        'record_count' => 10,
        'status' => DatasetStatus::Draft,
    ]);

    $batch = GenerationBatch::create([
        'dataset_version_id' => $version->id,
        'batch_number' => 1,
        'offset' => 0,
        'limit' => 10,
        'status' => BatchStatus::Pending,
    ]);

    expect($batch->status)->toBe(BatchStatus::Pending);
    $this->assertModelExists($batch);
});

test('DatasetRow model can be created with payload cast to array', function () {
    $user = User::factory()->create();
    $project = DatasetProject::create([
        'user_id' => $user->id,
        'name' => 'Test Project',
        'record_count' => 10,
        'status' => DatasetStatus::Draft,
    ]);
    $version = DatasetVersion::create([
        'dataset_project_id' => $project->id,
        'version_number' => 1,
        'record_count' => 10,
        'status' => DatasetStatus::Draft,
    ]);
    $batch = GenerationBatch::create([
        'dataset_version_id' => $version->id,
        'batch_number' => 1,
        'offset' => 0,
        'limit' => 10,
        'status' => BatchStatus::Pending,
    ]);

    $row = DatasetRow::create([
        'dataset_version_id' => $version->id,
        'generation_batch_id' => $batch->id,
        'row_index' => 0,
        'payload' => ['question' => 'What is AI?', 'answer' => 'Artificial Intelligence'],
        'is_valid' => true,
        'created_at' => now(),
    ]);

    expect($row->payload)->toBeArray()
        ->and($row->payload['question'])->toBe('What is AI?')
        ->and($row->is_valid)->toBeTrue();

    $this->assertModelExists($row);
});

// --- Relationships ---

test('AIProvider belongs to User', function () {
    $user = User::factory()->create();
    $provider = AIProvider::create([
        'user_id' => $user->id,
        'label' => 'My Provider',
        'type' => AIProviderType::OpenAI,
        'default_model' => 'gpt-4o',
    ]);

    expect($provider->user)->toBeInstanceOf(User::class)
        ->and($provider->user->id)->toBe($user->id);
});

test('DatasetProject belongs to User and has many DatasetVersions', function () {
    $user = User::factory()->create();
    $project = DatasetProject::create([
        'user_id' => $user->id,
        'name' => 'My Project',
        'record_count' => 10,
        'status' => DatasetStatus::Draft,
    ]);
    DatasetVersion::create([
        'dataset_project_id' => $project->id,
        'version_number' => 1,
        'record_count' => 10,
        'status' => DatasetStatus::Draft,
    ]);
    DatasetVersion::create([
        'dataset_project_id' => $project->id,
        'version_number' => 2,
        'record_count' => 10,
        'status' => DatasetStatus::Draft,
    ]);

    expect($project->user)->toBeInstanceOf(User::class)
        ->and($project->versions)->toHaveCount(2);
});

test('DatasetVersion has many GenerationBatches and DatasetRows', function () {
    $user = User::factory()->create();
    $project = DatasetProject::create([
        'user_id' => $user->id,
        'name' => 'My Project',
        'record_count' => 10,
        'status' => DatasetStatus::Draft,
    ]);
    $version = DatasetVersion::create([
        'dataset_project_id' => $project->id,
        'version_number' => 1,
        'record_count' => 10,
        'status' => DatasetStatus::Draft,
    ]);
    $batch = GenerationBatch::create([
        'dataset_version_id' => $version->id,
        'batch_number' => 1,
        'offset' => 0,
        'limit' => 10,
        'status' => BatchStatus::Pending,
    ]);
    DatasetRow::create([
        'dataset_version_id' => $version->id,
        'generation_batch_id' => $batch->id,
        'row_index' => 0,
        'payload' => ['key' => 'value'],
        'is_valid' => true,
        'created_at' => now(),
    ]);

    expect($version->batches)->toHaveCount(1)
        ->and($version->rows)->toHaveCount(1);
});

test('GenerationBatch belongs to DatasetVersion', function () {
    $user = User::factory()->create();
    $project = DatasetProject::create([
        'user_id' => $user->id,
        'name' => 'My Project',
        'record_count' => 10,
        'status' => DatasetStatus::Draft,
    ]);
    $version = DatasetVersion::create([
        'dataset_project_id' => $project->id,
        'version_number' => 1,
        'record_count' => 10,
        'status' => DatasetStatus::Draft,
    ]);
    $batch = GenerationBatch::create([
        'dataset_version_id' => $version->id,
        'batch_number' => 1,
        'offset' => 0,
        'limit' => 10,
        'status' => BatchStatus::Pending,
    ]);

    expect($batch->datasetVersion)->toBeInstanceOf(DatasetVersion::class)
        ->and($batch->datasetVersion->id)->toBe($version->id);
});

test('GenerationUsage belongs to all parent models', function () {
    $user = User::factory()->create();
    $provider = AIProvider::create([
        'user_id' => $user->id,
        'label' => 'Provider',
        'type' => AIProviderType::OpenAI,
        'default_model' => 'gpt-4o',
    ]);
    $project = DatasetProject::create([
        'user_id' => $user->id,
        'ai_provider_id' => $provider->id,
        'name' => 'My Project',
        'record_count' => 10,
        'status' => DatasetStatus::Draft,
    ]);
    $version = DatasetVersion::create([
        'dataset_project_id' => $project->id,
        'version_number' => 1,
        'record_count' => 10,
        'status' => DatasetStatus::Draft,
    ]);
    $batch = GenerationBatch::create([
        'dataset_version_id' => $version->id,
        'batch_number' => 1,
        'offset' => 0,
        'limit' => 10,
        'status' => BatchStatus::Pending,
    ]);

    $usage = GenerationUsage::create([
        'dataset_project_id' => $project->id,
        'dataset_version_id' => $version->id,
        'generation_batch_id' => $batch->id,
        'ai_provider_id' => $provider->id,
        'model' => 'gpt-4o',
        'prompt_tokens' => 100,
        'completion_tokens' => 200,
        'total_tokens' => 300,
        'estimated_cost' => 0.005,
        'latency_ms' => 1200,
        'created_at' => now(),
    ]);

    expect($usage->datasetProject)->toBeInstanceOf(DatasetProject::class)
        ->and($usage->datasetVersion)->toBeInstanceOf(DatasetVersion::class)
        ->and($usage->generationBatch)->toBeInstanceOf(GenerationBatch::class)
        ->and($usage->aiProvider)->toBeInstanceOf(AIProvider::class);
});
