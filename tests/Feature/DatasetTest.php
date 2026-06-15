<?php

use App\Actions\Datasets\CreateDatasetProjectAction;
use App\Actions\Datasets\UpdateDatasetProjectAction;
use App\Enums\DatasetStatus;
use App\Livewire\Datasets\DatasetForm;
use App\Livewire\Datasets\DatasetList;
use App\Models\DatasetProject;
use App\Models\DatasetVersion;
use App\Models\User;
use App\Services\Dataset\PromptBuilderService;
use Livewire\Livewire;

// --- CreateDatasetProjectAction ---

test('CreateDatasetProjectAction creates a project for user', function () {
    $user = User::factory()->create();

    $project = app(CreateDatasetProjectAction::class)->execute($user, [
        'name' => 'My Dataset',
        'record_count' => 50,
    ]);

    expect($project)->toBeInstanceOf(DatasetProject::class)
        ->and($project->user_id)->toBe($user->id)
        ->and($project->name)->toBe('My Dataset')
        ->and($project->record_count)->toBe(50)
        ->and($project->status)->toBe(DatasetStatus::Draft);
});

test('CreateDatasetProjectAction does not auto-create a DatasetVersion', function () {
    $user = User::factory()->create();

    $project = app(CreateDatasetProjectAction::class)->execute($user, [
        'name' => 'Versioned Dataset',
        'record_count' => 10,
    ]);

    expect(DatasetVersion::where('dataset_project_id', $project->id)->count())->toBe(0);
});

test('CreateDatasetProjectAction parses JSON schema string', function () {
    $user = User::factory()->create();
    $schema = json_encode(['type' => 'object', 'properties' => ['text' => ['type' => 'string']]]);

    $project = app(CreateDatasetProjectAction::class)->execute($user, [
        'name' => 'Schema Dataset',
        'record_count' => 10,
        'schema' => $schema,
    ]);

    expect($project->schema)->toBeArray()
        ->and($project->schema['type'])->toBe('object');
});

test('CreateDatasetProjectAction stores schema on project', function () {
    $user = User::factory()->create();
    $schema = ['type' => 'object', 'properties' => ['text' => ['type' => 'string']]];

    $project = app(CreateDatasetProjectAction::class)->execute($user, [
        'name' => 'Snapshot Dataset',
        'record_count' => 10,
        'schema' => json_encode($schema),
        'system_prompt' => 'Generate data.',
    ]);

    expect($project->system_prompt)->toBe('Generate data.')
        ->and($project->schema)->toBeArray();
});

// --- UpdateDatasetProjectAction ---

test('UpdateDatasetProjectAction updates project fields', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => $user->id, 'name' => 'Old Name']);

    $updated = app(UpdateDatasetProjectAction::class)->execute($project, ['name' => 'New Name']);

    expect($updated->name)->toBe('New Name');
});

// --- PromptBuilderService ---

test('PromptBuilderService builds PromptConfigDTO from project', function () {
    $project = DatasetProject::factory()->create([
        'system_prompt' => 'You are helpful.',
        'model' => 'gpt-4o',
        'temperature' => 0.5,
        'max_tokens' => 1024,
    ]);

    $dto = app(PromptBuilderService::class)->build($project);

    expect($dto->systemPrompt)->toBe('You are helpful.')
        ->and($dto->model)->toBe('gpt-4o')
        ->and($dto->temperature)->toBe(0.5)
        ->and($dto->maxTokens)->toBe(1024);
});

test('PromptBuilderService enables jsonMode when schema is set', function () {
    $project = DatasetProject::factory()->withSchema()->create();

    $dto = app(PromptBuilderService::class)->build($project);

    expect($dto->jsonMode)->toBeTrue()
        ->and($dto->schema)->toBeArray();
});

// --- Policy enforcement ---

test('user cannot view another users dataset project', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => User::factory()->create()->id]);

    expect($user->can('view', $project))->toBeFalse();
});

test('user can view their own dataset project', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => $user->id]);

    expect($user->can('view', $project))->toBeTrue();
});

test('user cannot update another users dataset project', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => User::factory()->create()->id]);

    expect($user->can('update', $project))->toBeFalse();
});

test('user cannot delete another users dataset project', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => User::factory()->create()->id]);

    expect($user->can('delete', $project))->toBeFalse();
});

// --- Routes ---

test('guests are redirected from datasets index', function () {
    $this->get(route('datasets.index'))->assertRedirect(route('login'));
});

test('guests are redirected from datasets create', function () {
    $this->get(route('datasets.create'))->assertRedirect(route('login'));
});

test('authenticated users can visit datasets index', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('datasets.index'))->assertOk();
});

test('authenticated users can visit datasets create', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('datasets.create'))->assertOk();
});

// --- Livewire components ---

test('DatasetList renders for authenticated user', function () {
    $user = User::factory()->create();
    DatasetProject::factory()->count(3)->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(DatasetList::class)
        ->assertOk();
});

test('DatasetList can delete own project', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(DatasetList::class)
        ->call('deleteProject', $project->id)
        ->assertOk();

    $this->assertSoftDeleted('dataset_projects', ['id' => $project->id]);
});

test('DatasetList cannot delete another users project', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => $other->id]);

    Livewire::actingAs($user)
        ->test(DatasetList::class)
        ->call('deleteProject', $project->id)
        ->assertForbidden();

    $this->assertDatabaseHas('dataset_projects', ['id' => $project->id]);
});

test('DatasetForm creates project on save and redirects to detail', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(DatasetForm::class)
        ->set('name', 'Test Dataset')
        ->set('recordCount', 20)
        ->call('save')
        ->assertRedirect(route('datasets.show', DatasetProject::where('user_id', $user->id)->where('name', 'Test Dataset')->first()));

    $project = DatasetProject::where('user_id', $user->id)->where('name', 'Test Dataset')->first();
    expect($project)->not->toBeNull()
        ->and($project->record_count)->toBe(20)
        ->and($project->status)->toBe(DatasetStatus::Draft);
});

test('DatasetForm validates required name', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(DatasetForm::class)
        ->set('name', '')
        ->set('recordCount', 10)
        ->call('save')
        ->assertHasErrors(['name']);
});

test('DatasetForm validates required record count', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(DatasetForm::class)
        ->set('name', 'Valid Name')
        ->set('recordCount', 0)
        ->call('save')
        ->assertHasErrors(['recordCount']);
});

test('DatasetForm loads existing project for edit', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => $user->id, 'name' => 'Existing']);

    Livewire::actingAs($user)
        ->test(DatasetForm::class, ['project' => $project])
        ->assertSet('name', 'Existing');
});

test('DatasetForm updates existing project', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => $user->id, 'name' => 'Old']);

    Livewire::actingAs($user)
        ->test(DatasetForm::class, ['project' => $project])
        ->set('name', 'Updated')
        ->call('save');

    expect($project->fresh()->name)->toBe('Updated');
});

test('DatasetForm blocks editing another users project', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => $other->id]);

    Livewire::actingAs($user)
        ->test(DatasetForm::class, ['project' => $project])
        ->assertForbidden();
});
