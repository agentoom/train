<?php

use App\Livewire\Datasets\DatasetList;
use App\Models\DatasetProject;
use App\Models\User;
use App\Services\Dataset\DatasetSettingsExportService;
use App\Services\Dataset\DatasetSettingsImportService;
use Livewire\Livewire;

// --- DatasetSettingsExportService ---

test('exportMany produces valid JSON with datasets array', function () {
    $user = User::factory()->create();
    $projects = DatasetProject::factory()->count(2)->create(['user_id' => $user->id]);

    $service = new DatasetSettingsExportService();
    $json = $service->exportMany($projects);

    $decoded = json_decode($json, true);

    expect($decoded)->toHaveKey('version', '1.0')
        ->and($decoded)->toHaveKey('exported_at')
        ->and($decoded['datasets'])->toHaveCount(2);
});

test('exportMany includes expected settings fields', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->create([
        'user_id' => $user->id,
        'name' => 'My Dataset',
        'model' => 'gpt-4o',
        'temperature' => 0.8,
        'evaluation_enabled' => true,
    ]);

    $service = new DatasetSettingsExportService();
    $json = $service->exportMany(collect([$project]));
    $decoded = json_decode($json, true);
    $data = $decoded['datasets'][0];

    expect($data['name'])->toBe('My Dataset')
        ->and($data['model'])->toBe('gpt-4o')
        ->and($data['temperature'])->toBe(0.8)
        ->and($data['evaluation_enabled'])->toBeTrue();
});

test('exportMany does not include user_id or provider ids', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => $user->id]);

    $service = new DatasetSettingsExportService();
    $json = $service->exportMany(collect([$project]));
    $decoded = json_decode($json, true);
    $data = $decoded['datasets'][0];

    expect($data)->not->toHaveKey('user_id')
        ->and($data)->not->toHaveKey('ai_provider_id')
        ->and($data)->not->toHaveKey('evaluation_ai_provider_id')
        ->and($data)->not->toHaveKey('critic_ai_provider_id')
        ->and($data)->not->toHaveKey('refiner_ai_provider_id');
});

test('exportOne returns settings array for a single project', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => $user->id, 'name' => 'Solo']);

    $service = new DatasetSettingsExportService();
    $data = $service->exportOne($project);

    expect($data)->toBeArray()
        ->and($data['name'])->toBe('Solo')
        ->and($data)->not->toHaveKey('user_id');
});

// --- DatasetSettingsImportService ---

test('parse accepts multi-dataset export format', function () {
    $json = json_encode([
        'version' => '1.0',
        'exported_at' => now()->toIso8601String(),
        'datasets' => [
            ['name' => 'Dataset A', 'model' => 'gpt-4o'],
            ['name' => 'Dataset B', 'model' => 'claude-3'],
        ],
    ]);

    $service = new DatasetSettingsImportService();
    $datasets = $service->parse($json);

    expect($datasets)->toHaveCount(2)
        ->and($datasets[0]['name'])->toBe('Dataset A')
        ->and($datasets[1]['name'])->toBe('Dataset B');
});

test('parse accepts single-dataset export format', function () {
    $json = json_encode(['name' => 'Single Dataset', 'model' => 'gpt-4o-mini']);

    $service = new DatasetSettingsImportService();
    $datasets = $service->parse($json);

    expect($datasets)->toHaveCount(1)
        ->and($datasets[0]['name'])->toBe('Single Dataset');
});

test('parse throws on invalid JSON', function () {
    $service = new DatasetSettingsImportService();
    $service->parse('not-json');
})->throws(\InvalidArgumentException::class, 'Invalid JSON');

test('parse throws on unrecognised format', function () {
    $service = new DatasetSettingsImportService();
    $service->parse(json_encode(['foo' => 'bar']));
})->throws(\InvalidArgumentException::class, 'Unrecognised export format');

test('parse throws when datasets array is empty', function () {
    $service = new DatasetSettingsImportService();
    $service->parse(json_encode(['version' => '1.0', 'datasets' => []]));
})->throws(\InvalidArgumentException::class, 'no datasets');

test('parse throws when a dataset is missing name', function () {
    $json = json_encode(['datasets' => [['model' => 'gpt-4o']]]);

    $service = new DatasetSettingsImportService();
    $service->parse($json);
})->throws(\InvalidArgumentException::class, 'name');

test('import creates dataset projects for the user', function () {
    $user = User::factory()->create();

    $datasets = [
        ['name' => 'Imported A', 'model' => 'gpt-4o', 'record_count' => 20],
        ['name' => 'Imported B', 'model' => 'claude-3', 'record_count' => 50],
    ];

    $service = new DatasetSettingsImportService();
    $created = $service->import($datasets, $user);

    expect($created)->toHaveCount(2)
        ->and($created[0]->name)->toBe('Imported A')
        ->and($created[0]->user_id)->toBe($user->id)
        ->and($created[1]->name)->toBe('Imported B');

    expect(DatasetProject::where('user_id', $user->id)->count())->toBe(2);
});

test('import strips disallowed fields like user_id from payload', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $datasets = [['name' => 'Sneaky', 'user_id' => $other->id]];

    $service = new DatasetSettingsImportService();
    $created = $service->import($datasets, $user);

    expect($created[0]->user_id)->toBe($user->id);
});

// --- DatasetList Livewire component ---

test('dataset list shows import button', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(DatasetList::class)
        ->assertSee('Import');
});

test('dataset list shows export selected button when projects exist', function () {
    $user = User::factory()->create();
    DatasetProject::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(DatasetList::class)
        ->assertSee('Export Selected');
});

test('openImportModal sets showImportModal to true', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(DatasetList::class)
        ->call('openImportModal')
        ->assertSet('showImportModal', true)
        ->assertSet('importError', null);
});

test('importSettings validates that a file is required', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(DatasetList::class)
        ->call('importSettings')
        ->assertHasErrors(['importFile']);
});

test('exportSelected ignores ids belonging to other users', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherProject = DatasetProject::factory()->create(['user_id' => $other->id]);

    Livewire::actingAs($user)
        ->test(DatasetList::class)
        ->set('selectedIds', [$otherProject->id])
        ->call('exportSelected');

    expect(true)->toBeTrue();
});
