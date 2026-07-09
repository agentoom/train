<?php

use App\DTOs\FailureAnalysisDTO;
use App\DTOs\FeedbackReportDTO;
use App\DTOs\GenerationControlSignalDTO;
use App\Enums\SignalPriority;
use App\Enums\SignalType;
use App\Enums\TargetComponent;
use App\Models\DatasetEvaluationReport;
use App\Models\DatasetFeedbackReport;
use App\Models\DatasetProject;
use App\Models\DatasetVersion;
use App\Models\User;
use App\Services\DatasetEvaluation\DatasetEvaluationService;
use App\Services\DatasetEvaluation\Feedback\DatasetEvaluationFeedbackService;
use App\Services\DatasetEvaluation\Feedback\DatasetImprovementSignalBuilder;
use App\Services\DatasetEvaluation\Feedback\FailureAnalysisEngine;
use App\Services\DatasetEvaluation\Feedback\FeedbackReportRepository;

// ---------------------------------------------------------------------------
// FailureAnalysisEngine
// ---------------------------------------------------------------------------

test('FailureAnalysisEngine returns no failures for a passing report', function () {
    $report = DatasetEvaluationReport::factory()->create([
        'overall_score' => 85.0,
        'passed' => true,
        'evaluator_scores' => [
            'task_performance' => ['score' => 90, 'passed' => true, 'summary' => 'ok', 'details' => []],
            'conversation_quality' => ['score' => 88, 'passed' => true, 'summary' => 'ok', 'details' => []],
            'distribution_drift' => ['score' => 80, 'passed' => true, 'summary' => 'ok', 'details' => []],
            'negative_ratio' => ['score' => 75, 'passed' => true, 'summary' => 'ok', 'details' => []],
            'edge_case' => ['score' => 70, 'passed' => true, 'summary' => 'ok', 'details' => []],
        ],
    ]);

    $engine = new FailureAnalysisEngine;
    $result = $engine->analyse($report);

    expect($result)->toBeInstanceOf(FailureAnalysisDTO::class)
        ->and($result->failuresByType)->toBeEmpty()
        ->and($result->systemic)->toBeEmpty()
        ->and($result->hasFailures())->toBeFalse();
});

test('FailureAnalysisEngine groups failures by type for failing evaluators', function () {
    $report = DatasetEvaluationReport::factory()->create([
        'overall_score' => 45.0,
        'passed' => false,
        'evaluator_scores' => [
            'task_performance' => ['score' => 40, 'passed' => false, 'summary' => 'bad', 'details' => []],
            'negative_ratio' => ['score' => 30, 'passed' => false, 'summary' => 'bad', 'details' => ['negative_ratio' => 0.01]],
            'conversation_quality' => ['score' => 90, 'passed' => true, 'summary' => 'ok', 'details' => []],
            'distribution_drift' => ['score' => 85, 'passed' => true, 'summary' => 'ok', 'details' => []],
            'edge_case' => ['score' => 80, 'passed' => true, 'summary' => 'ok', 'details' => []],
        ],
    ]);

    $engine = new FailureAnalysisEngine;
    $result = $engine->analyse($report);

    expect($result->failuresByType)->toHaveKeys(['data_quality', 'negative_ratio_imbalance'])
        ->and($result->hasFailures())->toBeTrue();
});

test('FailureAnalysisEngine detects systemic issues when multiple evaluators fail', function () {
    $report = DatasetEvaluationReport::factory()->create([
        'overall_score' => 35.0,
        'passed' => false,
        'evaluator_scores' => [
            'task_performance' => ['score' => 30, 'passed' => false, 'summary' => 'bad', 'details' => []],
            'negative_ratio' => ['score' => 25, 'passed' => false, 'summary' => 'bad', 'details' => ['negative_ratio' => 0.01]],
            'edge_case' => ['score' => 20, 'passed' => false, 'summary' => 'bad', 'details' => []],
            'conversation_quality' => ['score' => 90, 'passed' => true, 'summary' => 'ok', 'details' => []],
            'distribution_drift' => ['score' => 85, 'passed' => true, 'summary' => 'ok', 'details' => []],
        ],
    ]);

    $engine = new FailureAnalysisEngine;
    $result = $engine->analyse($report);

    expect($result->systemic)->not->toBeEmpty();
});

test('FailureAnalysisEngine toArray returns expected keys', function () {
    $report = DatasetEvaluationReport::factory()->create([
        'overall_score' => 80.0,
        'passed' => true,
        'evaluator_scores' => [],
    ]);

    $engine = new FailureAnalysisEngine;
    $array = $engine->analyse($report)->toArray();

    expect($array)->toHaveKeys(['failures_by_type', 'systemic_issues', 'evaluator_scores']);
});

// ---------------------------------------------------------------------------
// DatasetImprovementSignalBuilder
// ---------------------------------------------------------------------------

test('SignalBuilder emits INCREASE_NEGATIVE_SAMPLING when ratio is too low', function () {
    $report = DatasetEvaluationReport::factory()->create([
        'overall_score' => 50.0,
        'passed' => false,
        'duplicate_rate' => 0.0,
        'evaluator_scores' => [
            'negative_ratio' => ['score' => 30, 'passed' => false, 'summary' => 'bad', 'details' => ['negative_ratio' => 0.01]],
        ],
    ]);

    $analysis = (new FailureAnalysisEngine)->analyse($report);
    $signals = (new DatasetImprovementSignalBuilder)->build($analysis, $report);

    $types = array_map(fn (GenerationControlSignalDTO $s) => $s->signalType, $signals);
    expect($types)->toContain(SignalType::IncreaseNegativeSampling);
});

test('SignalBuilder emits INJECT_EDGE_CASES when edge case score is low', function () {
    $report = DatasetEvaluationReport::factory()->create([
        'overall_score' => 50.0,
        'passed' => false,
        'duplicate_rate' => 0.0,
        'evaluator_scores' => [
            'edge_case' => ['score' => 30, 'passed' => false, 'summary' => 'bad', 'details' => []],
        ],
    ]);

    $analysis = (new FailureAnalysisEngine)->analyse($report);
    $signals = (new DatasetImprovementSignalBuilder)->build($analysis, $report);

    $types = array_map(fn (GenerationControlSignalDTO $s) => $s->signalType, $signals);
    expect($types)->toContain(SignalType::InjectEdgeCases);
});

test('SignalBuilder emits REDUCE_DUPLICATES when duplicate rate is high', function () {
    $report = DatasetEvaluationReport::factory()->create([
        'overall_score' => 50.0,
        'passed' => false,
        'duplicate_rate' => 0.30,
        'evaluator_scores' => [
            'distribution_drift' => ['score' => 40, 'passed' => false, 'summary' => 'bad', 'details' => ['duplicate_rate' => 0.30]],
        ],
    ]);

    $analysis = (new FailureAnalysisEngine)->analyse($report);
    $signals = (new DatasetImprovementSignalBuilder)->build($analysis, $report);

    $types = array_map(fn (GenerationControlSignalDTO $s) => $s->signalType, $signals);
    expect($types)->toContain(SignalType::ReduceDuplicates);
});

test('SignalBuilder emits no signals for a healthy report', function () {
    $report = DatasetEvaluationReport::factory()->create([
        'overall_score' => 90.0,
        'passed' => true,
        'duplicate_rate' => 0.01,
        'evaluator_scores' => [
            'task_performance' => ['score' => 90, 'passed' => true, 'summary' => 'ok', 'details' => []],
            'conversation_quality' => ['score' => 88, 'passed' => true, 'summary' => 'ok', 'details' => []],
            'distribution_drift' => ['score' => 85, 'passed' => true, 'summary' => 'ok', 'details' => []],
            'negative_ratio' => ['score' => 80, 'passed' => true, 'summary' => 'ok', 'details' => ['negative_ratio' => 0.15]],
            'edge_case' => ['score' => 75, 'passed' => true, 'summary' => 'ok', 'details' => []],
        ],
    ]);

    $analysis = (new FailureAnalysisEngine)->analyse($report);
    $signals = (new DatasetImprovementSignalBuilder)->build($analysis, $report);

    expect($signals)->toBeEmpty();
});

test('SignalBuilder signal strength is between 0 and 1', function () {
    $report = DatasetEvaluationReport::factory()->create([
        'overall_score' => 40.0,
        'passed' => false,
        'duplicate_rate' => 0.0,
        'evaluator_scores' => [
            'task_performance' => ['score' => 10, 'passed' => false, 'summary' => 'bad', 'details' => []],
        ],
    ]);

    $analysis = (new FailureAnalysisEngine)->analyse($report);
    $signals = (new DatasetImprovementSignalBuilder)->build($analysis, $report);

    foreach ($signals as $signal) {
        expect($signal->strength)->toBeGreaterThanOrEqual(0.0)
            ->and($signal->strength)->toBeLessThanOrEqual(1.0);
    }
});

test('GenerationControlSignalDTO toArray returns all required keys', function () {
    $signal = new GenerationControlSignalDTO(
        signalType: SignalType::InjectEdgeCases,
        strength: 0.20,
        targetComponent: TargetComponent::Augmentation,
        priority: SignalPriority::Medium,
        explanation: 'EdgeCaseEvaluator score 45/100.',
    );

    $array = $signal->toArray();

    expect($array)->toHaveKeys(['type', 'strength', 'target_component', 'priority', 'explanation'])
        ->and($array['type'])->toBe('INJECT_EDGE_CASES')
        ->and($array['target_component'])->toBe('augmentation')
        ->and($array['priority'])->toBe('medium');
});

// ---------------------------------------------------------------------------
// FeedbackReportRepository
// ---------------------------------------------------------------------------

test('FeedbackReportRepository stores a feedback report', function () {
    $evalReport = DatasetEvaluationReport::factory()->create([
        'overall_score' => 55.0,
        'passed' => false,
        'evaluator_scores' => [],
    ]);

    $analysis = new FailureAnalysisDTO(
        failuresByType: ['data_quality' => [['evaluator' => 'task_performance', 'score' => 40, 'summary' => 'bad', 'details' => []]]],
        systemic: ['data_quality'],
        rawEvaluatorScores: [],
    );

    $signal = new GenerationControlSignalDTO(
        signalType: SignalType::ReviewGenerationPrompt,
        strength: 0.38,
        targetComponent: TargetComponent::Generation,
        priority: SignalPriority::Medium,
        explanation: 'TaskPerformanceEvaluator score 40/100.',
    );

    $dto = new FeedbackReportDTO(
        evaluationReportId: $evalReport->id,
        failureAnalysis: $analysis,
        signals: [$signal],
        systemHealthScore: 50.0,
    );

    $repo = new FeedbackReportRepository;
    $stored = $repo->store($dto);

    expect($stored)->toBeInstanceOf(DatasetFeedbackReport::class)
        ->and($stored->evaluation_run_id)->toBe($evalReport->id)
        ->and($stored->system_health_score)->toBe(50.0)
        ->and($stored->improvement_signals_json)->toHaveCount(1);
});

test('FeedbackReportRepository finds report by evaluation report id', function () {
    $evalReport = DatasetEvaluationReport::factory()->create(['overall_score' => 60.0, 'passed' => false, 'evaluator_scores' => []]);
    $feedback = DatasetFeedbackReport::factory()->create(['evaluation_run_id' => $evalReport->id]);

    $repo = new FeedbackReportRepository;
    $found = $repo->findByEvaluationReportId($evalReport->id);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($feedback->id);
});

// ---------------------------------------------------------------------------
// DatasetEvaluationFeedbackService (orchestrator)
// ---------------------------------------------------------------------------

test('DatasetEvaluationFeedbackService process persists a feedback report', function () {
    $evalReport = DatasetEvaluationReport::factory()->create([
        'overall_score' => 50.0,
        'passed' => false,
        'duplicate_rate' => 0.0,
        'evaluator_scores' => [
            'task_performance' => ['score' => 40, 'passed' => false, 'summary' => 'bad', 'details' => []],
            'negative_ratio' => ['score' => 30, 'passed' => false, 'summary' => 'bad', 'details' => ['negative_ratio' => 0.01]],
        ],
    ]);

    $service = new DatasetEvaluationFeedbackService(
        new FailureAnalysisEngine,
        new DatasetImprovementSignalBuilder,
        new FeedbackReportRepository,
    );

    $result = $service->process($evalReport);

    expect($result)->toBeInstanceOf(DatasetFeedbackReport::class)
        ->and($result->evaluation_run_id)->toBe($evalReport->id)
        ->and($result->system_health_score)->toBeLessThanOrEqual($evalReport->overall_score);

    $this->assertDatabaseHas('dataset_feedback_reports', [
        'evaluation_run_id' => $evalReport->id,
    ]);
});

test('DatasetEvaluationFeedbackService system health score is penalised per signal', function () {
    $evalReport = DatasetEvaluationReport::factory()->create([
        'overall_score' => 70.0,
        'passed' => false,
        'duplicate_rate' => 0.0,
        'evaluator_scores' => [
            'task_performance' => ['score' => 40, 'passed' => false, 'summary' => 'bad', 'details' => []],
        ],
    ]);

    $service = new DatasetEvaluationFeedbackService(
        new FailureAnalysisEngine,
        new DatasetImprovementSignalBuilder,
        new FeedbackReportRepository,
    );

    $result = $service->process($evalReport);

    expect($result->system_health_score)->toBeLessThan(70.0);
});

// ---------------------------------------------------------------------------
// Phase 1.5 optional hook
// ---------------------------------------------------------------------------

test('Phase 1.5 evaluate does NOT call feedback service when config is disabled', function () {
    config(['dataset_evaluation.feedback_enabled' => false]);

    $user = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => $user->id]);
    $version = DatasetVersion::factory()->create(['dataset_project_id' => $project->id]);

    $mockFeedback = Mockery::mock(DatasetEvaluationFeedbackService::class);
    $mockFeedback->shouldNotReceive('process');
    $this->app->instance(DatasetEvaluationFeedbackService::class, $mockFeedback);

    $service = new DatasetEvaluationService;
    $service->evaluate($version);

    expect(true)->toBeTrue();
});

test('Phase 1.5 evaluate calls feedback service when config is enabled', function () {
    config(['dataset_evaluation.feedback_enabled' => true]);

    $user = User::factory()->create();
    $project = DatasetProject::factory()->create(['user_id' => $user->id]);
    $version = DatasetVersion::factory()->create(['dataset_project_id' => $project->id]);

    $mockFeedback = Mockery::mock(DatasetEvaluationFeedbackService::class);
    $mockFeedback->shouldReceive('process')->once()->andReturn(
        DatasetFeedbackReport::factory()->make(),
    );
    $this->app->instance(DatasetEvaluationFeedbackService::class, $mockFeedback);

    $service = new DatasetEvaluationService;
    $service->evaluate($version);

    expect(true)->toBeTrue();
})->afterEach(function () {
    config(['dataset_evaluation.feedback_enabled' => false]);
});
