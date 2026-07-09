<?php

use App\Enums\DatasetStatus;
use App\Livewire\Dashboard\Overview;
use App\Models\AIProvider;
use App\Models\DatasetProject;
use App\Models\DatasetRow;
use App\Models\DatasetVersion;
use App\Models\GenerationBatch;
use App\Models\GenerationUsage;
use App\Models\User;
use Livewire\Livewire;

// ---------------------------------------------------------------------------
// Export tests
// ---------------------------------------------------------------------------

test('export route requires authentication', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->for($user)->create();

    $this->get(route('datasets.export', $project))->assertRedirect(route('login'));
});

test('export route is forbidden for another user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = DatasetProject::factory()->for($owner)->create();

    $this->actingAs($other)
        ->get(route('datasets.export', $project))
        ->assertForbidden();
});

test('export produces valid JSON for a project with rows', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->for($user)->completed()->create();
    $version = DatasetVersion::factory()->for($project)->create(['version_number' => 1, 'status' => DatasetStatus::Completed]);
    $batch = GenerationBatch::factory()->for($version)->create();

    DatasetRow::create(['dataset_version_id' => $version->id, 'generation_batch_id' => $batch->id, 'row_index' => 0, 'payload' => ['text' => 'hello'], 'is_valid' => true]);
    DatasetRow::create(['dataset_version_id' => $version->id, 'generation_batch_id' => $batch->id, 'row_index' => 1, 'payload' => ['text' => 'world'], 'is_valid' => true]);

    $response = $this->actingAs($user)
        ->get(route('datasets.export', ['project' => $project, 'format' => 'json']));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/json');

    $decoded = json_decode($response->streamedContent(), true);
    expect($decoded)->toBeArray()->toHaveCount(2);
    expect($decoded[0]['text'])->toBe('hello');
    expect($decoded[1]['text'])->toBe('world');
});

test('export produces valid JSONL for a project with rows', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->for($user)->completed()->create();
    $version = DatasetVersion::factory()->for($project)->create(['version_number' => 1, 'status' => DatasetStatus::Completed]);
    $batch = GenerationBatch::factory()->for($version)->create();

    DatasetRow::create(['dataset_version_id' => $version->id, 'generation_batch_id' => $batch->id, 'row_index' => 0, 'payload' => ['text' => 'line1'], 'is_valid' => true]);

    $response = $this->actingAs($user)
        ->get(route('datasets.export', ['project' => $project, 'format' => 'jsonl']));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/jsonl');

    $lines = array_values(array_filter(explode("\n", trim($response->streamedContent()))));
    expect($lines)->toHaveCount(1);
    expect(json_decode($lines[0], true)['text'])->toBe('line1');
});

test('export produces valid CSV for a project with rows', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->for($user)->completed()->create();
    $version = DatasetVersion::factory()->for($project)->create(['version_number' => 1, 'status' => DatasetStatus::Completed]);
    $batch = GenerationBatch::factory()->for($version)->create();

    DatasetRow::create(['dataset_version_id' => $version->id, 'generation_batch_id' => $batch->id, 'row_index' => 0, 'payload' => ['name' => 'Alice', 'age' => 30], 'is_valid' => true]);

    $response = $this->actingAs($user)
        ->get(route('datasets.export', ['project' => $project, 'format' => 'csv']));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');

    $lines = array_values(array_filter(explode("\n", trim($response->streamedContent()))));
    expect($lines)->toHaveCount(2); // header + 1 data row
});

test('export of empty dataset returns valid empty JSON array', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->for($user)->create();
    DatasetVersion::factory()->for($project)->create(['version_number' => 1]);

    $response = $this->actingAs($user)
        ->get(route('datasets.export', ['project' => $project, 'format' => 'json']));

    $response->assertOk();
    expect(json_decode($response->streamedContent(), true))->toBe([]);
});

test('export of empty dataset returns valid empty JSONL', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->for($user)->create();
    DatasetVersion::factory()->for($project)->create(['version_number' => 1]);

    $response = $this->actingAs($user)
        ->get(route('datasets.export', ['project' => $project, 'format' => 'jsonl']));

    $response->assertOk();
    expect(trim($response->streamedContent()))->toBe('');
});

test('export defaults to JSON format when format param is missing', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->for($user)->create();
    DatasetVersion::factory()->for($project)->create(['version_number' => 1]);

    $response = $this->actingAs($user)->get(route('datasets.export', $project));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/json');
});

test('export targets the latest version', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->for($user)->create();

    DatasetVersion::factory()->for($project)->create(['version_number' => 1]);
    $v2 = DatasetVersion::factory()->for($project)->create(['version_number' => 2]);
    $batch = GenerationBatch::factory()->for($v2)->create();

    DatasetRow::create(['dataset_version_id' => $v2->id, 'generation_batch_id' => $batch->id, 'row_index' => 0, 'payload' => ['version' => 'v2'], 'is_valid' => true]);

    $response = $this->actingAs($user)
        ->get(route('datasets.export', ['project' => $project, 'format' => 'json']));

    $decoded = json_decode($response->streamedContent(), true);
    expect($decoded[0]['version'])->toBe('v2');
});

// ---------------------------------------------------------------------------
// Dashboard Overview tests
// ---------------------------------------------------------------------------

test('dashboard overview renders for authenticated user', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Overview::class)
        ->assertOk();
});

test('dashboard overview shows correct running jobs count', function () {
    $user = User::factory()->create();

    DatasetProject::factory()->for($user)->create(['status' => DatasetStatus::Running]);
    DatasetProject::factory()->for($user)->create(['status' => DatasetStatus::Running]);
    DatasetProject::factory()->for($user)->completed()->create();

    Livewire::actingAs($user)
        ->test(Overview::class)
        ->assertSee('2');
});

test('dashboard overview shows correct completed datasets count', function () {
    $user = User::factory()->create();

    DatasetProject::factory()->for($user)->completed()->create();
    DatasetProject::factory()->for($user)->completed()->create();
    DatasetProject::factory()->for($user)->create(['status' => DatasetStatus::Draft]);

    Livewire::actingAs($user)
        ->test(Overview::class)
        ->assertSee('2');
});

test('dashboard overview shows today token usage from GenerationUsage', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->for($user)->create();
    $version = DatasetVersion::factory()->for($project)->create(['version_number' => 1]);
    $batch = GenerationBatch::factory()->for($version)->create();
    $provider = AIProvider::factory()->for($user)->create();

    GenerationUsage::create([
        'dataset_project_id' => $project->id,
        'dataset_version_id' => $version->id,
        'generation_batch_id' => $batch->id,
        'ai_provider_id' => $provider->id,
        'model' => 'gpt-4o-mini',
        'prompt_tokens' => 100,
        'completion_tokens' => 200,
        'total_tokens' => 300,
        'estimated_cost' => 0.001,
        'latency_ms' => 500,
        'created_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(Overview::class)
        ->assertSee('300');
});

test('dashboard overview does not show other users data', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    DatasetProject::factory()->for($other)->create(['status' => DatasetStatus::Running]);

    Livewire::actingAs($user)
        ->test(Overview::class)
        ->assertSee('Running Jobs');
});

test('dashboard overview shows active providers', function () {
    $user = User::factory()->create();
    AIProvider::factory()->for($user)->create(['label' => 'My OpenAI', 'is_enabled' => true]);

    Livewire::actingAs($user)
        ->test(Overview::class)
        ->assertSee('My OpenAI');
});
