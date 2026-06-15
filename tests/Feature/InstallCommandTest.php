<?php

use App\Models\DatasetProject;
use App\Models\User;

test('install command creates the default superadmin user', function () {
    $this->artisan('agentoom-train:install')
        ->expectsOutput('Agentoom Train installed successfully.')
        ->assertExitCode(0);

    expect(User::where('email', 'superadmin@agentoom.com')->where('name', 'superadmin')->exists())->toBeTrue();
});

test('install command skips creation when superadmin already exists', function () {
    User::factory()->create(['email' => 'superadmin@agentoom.com']);

    $this->artisan('agentoom-train:install')
        ->expectsOutput('Default superadmin user already exists. Skipping creation.')
        ->assertExitCode(0);

    expect(User::where('email', 'superadmin@agentoom.com')->count())->toBe(1);
});

test('install command imports default datasets from config', function () {
    $json = json_encode([
        'version' => '1.0',
        'exported_at' => now()->toIso8601String(),
        'datasets' => [
            ['name' => 'Test Dataset Alpha', 'record_count' => 10, 'chunk_size' => 5, 'model' => 'openai/gpt-4.1-mini'],
            ['name' => 'Test Dataset Beta', 'record_count' => 20, 'chunk_size' => 5, 'model' => 'openai/gpt-4.1-mini'],
        ],
    ]);

    config(['agentoom-train-data' => [$json]]);

    $this->artisan('agentoom-train:install')
        ->expectsOutput('Imported 2 default dataset(s) from config.')
        ->assertExitCode(0);

    $user = User::where('email', 'superadmin@agentoom.com')->first();

    expect(DatasetProject::where('user_id', $user->id)->count())->toBe(2)
        ->and(DatasetProject::where('user_id', $user->id)->where('name', 'Test Dataset Alpha')->exists())->toBeTrue()
        ->and(DatasetProject::where('user_id', $user->id)->where('name', 'Test Dataset Beta')->exists())->toBeTrue();
});

test('install command gracefully handles missing config data', function () {
    config(['agentoom-train-data' => []]);

    $this->artisan('agentoom-train:install')
        ->expectsOutput('Agentoom Train installed successfully.')
        ->assertExitCode(0);

    $user = User::where('email', 'superadmin@agentoom.com')->first();
    expect(DatasetProject::where('user_id', $user->id)->count())->toBe(0);
});
