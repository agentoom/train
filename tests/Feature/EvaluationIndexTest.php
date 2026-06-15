<?php

use App\Livewire\Evaluation\EvaluationIndex;
use App\Models\DatasetEvaluationReport;
use App\Models\DatasetProject;
use App\Models\DatasetVersion;
use App\Models\User;
use App\Services\DatasetEvaluation\DatasetEvaluationService;
use Livewire\Livewire;

test('evaluation page requires authentication', function () {
    $this->get(route('evaluation.index'))->assertRedirect(route('login'));
});

test('evaluation page renders for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('evaluation.index'))
        ->assertOk()
        ->assertSeeLivewire(EvaluationIndex::class);
});

test('evaluation page shows empty state when no versions exist', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(EvaluationIndex::class)
        ->assertSee('No dataset versions available yet');
});

test('evaluation page lists dataset versions belonging to the user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $project = DatasetProject::factory()->create(['user_id' => $user->id]);
    DatasetVersion::factory()->create(['dataset_project_id' => $project->id]);

    $otherProject = DatasetProject::factory()->create(['user_id' => $other->id]);
    DatasetVersion::factory()->create(['dataset_project_id' => $otherProject->id]);

    Livewire::actingAs($user)
        ->test(EvaluationIndex::class)
        ->assertSee($project->name)
        ->assertDontSee($otherProject->name);
});

test('evaluation page shows existing reports', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => $user->id]);
    $version = DatasetVersion::factory()->create(['dataset_project_id' => $project->id]);

    DatasetEvaluationReport::factory()->create([
        'dataset_version_id' => $version->id,
        'overall_score' => 78.5,
        'passed' => true,
        'verdict' => 'Dataset passed all evaluators.',
    ]);

    Livewire::actingAs($user)
        ->test(EvaluationIndex::class)
        ->assertSee('78.5')
        ->assertSee('Passed')
        ->assertSee('Dataset passed all evaluators.');
});

test('runEvaluation shows error when no version selected', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(EvaluationIndex::class)
        ->call('runEvaluation')
        ->assertSet('errorMessage', 'Please select a dataset version to evaluate.');
});

test('runEvaluation calls evaluation service and clears selection', function () {
    $user = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => $user->id]);
    $version = DatasetVersion::factory()->create(['dataset_project_id' => $project->id]);

    $report = DatasetEvaluationReport::factory()->create([
        'dataset_version_id' => $version->id,
        'overall_score' => 80.0,
        'passed' => true,
        'verdict' => 'All good.',
    ]);

    $mockService = Mockery::mock(DatasetEvaluationService::class);
    $mockService->shouldReceive('evaluate')->once()->andReturn($report);
    $this->app->instance(DatasetEvaluationService::class, $mockService);

    Livewire::actingAs($user)
        ->test(EvaluationIndex::class)
        ->set('selectedVersionId', $version->id)
        ->call('runEvaluation')
        ->assertSet('selectedVersionId', null)
        ->assertSet('errorMessage', null)
        ->assertDispatched('evaluation-complete');
});

test('runEvaluation rejects version belonging to another user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $otherProject = DatasetProject::factory()->create(['user_id' => $other->id]);
    $otherVersion = DatasetVersion::factory()->create(['dataset_project_id' => $otherProject->id]);

    Livewire::actingAs($user)
        ->test(EvaluationIndex::class)
        ->set('selectedVersionId', $otherVersion->id)
        ->call('runEvaluation')
        ->assertSet('errorMessage', 'Dataset version not found or access denied.');
});
