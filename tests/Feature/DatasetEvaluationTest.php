<?php

use App\DTOs\BatchEvaluationResultDTO;
use App\DTOs\DatasetBatchDTO;
use App\Models\DatasetEvaluationReport;
use App\Models\DatasetProject;
use App\Models\DatasetReleaseVersion;
use App\Models\DatasetRow;
use App\Models\DatasetVersion;
use App\Models\AIProvider;
use App\Models\User;
use App\Services\DatasetEvaluation\DatasetEvaluationService;
use App\Services\DatasetEvaluation\EvaluationPipeline;
use App\Services\DatasetEvaluation\Evaluators\ConversationQualityEvaluator;
use App\Services\DatasetEvaluation\Evaluators\DistributionDriftEvaluator;
use App\Services\DatasetEvaluation\Evaluators\EdgeCaseEvaluator;
use App\Services\DatasetEvaluation\Evaluators\NegativeRatioEvaluator;
use App\Services\DatasetEvaluation\Evaluators\TaskPerformanceEvaluator;
use Illuminate\Support\Collection;

// --- Helpers ---

function makeBatch(array $rows = [], array $metadata = [], ?DatasetVersion $version = null): DatasetBatchDTO
{
    if ($version === null) {
        $user = User::factory()->create();
        $provider = AIProvider::factory()->create(['user_id' => $user->id]);
        $project = DatasetProject::factory()->create(['user_id' => $user->id, 'ai_provider_id' => $provider->id]);
        $version = DatasetVersion::factory()->create(['dataset_project_id' => $project->id, 'ai_provider_id' => $provider->id]);
    }

    return new DatasetBatchDTO(
        version: $version,
        rows: Collection::make($rows),
        schema: null,
        metadata: $metadata,
    );
}

// --- TaskPerformanceEvaluator ---

test('TaskPerformanceEvaluator returns zero score for empty batch', function () {
    $result = (new TaskPerformanceEvaluator())->evaluate(makeBatch());

    expect($result->evaluator)->toBe('task_performance')
        ->and($result->score)->toBe(0.0)
        ->and($result->passed)->toBeFalse();
});

test('TaskPerformanceEvaluator scores high for all-valid rows with quality scores', function () {
    $rows = array_fill(0, 5, ['is_valid' => true, 'evaluation_failed' => false, 'quality_score' => 90]);
    $result = (new TaskPerformanceEvaluator())->evaluate(makeBatch($rows));

    expect($result->passed)->toBeTrue()
        ->and($result->score)->toBeGreaterThan(70.0)
        ->and($result->details['valid_rows'])->toBe(5)
        ->and($result->details['evaluation_failures'])->toBe(0);
});

test('TaskPerformanceEvaluator penalises invalid and failed rows', function () {
    $rows = [
        ['is_valid' => false, 'evaluation_failed' => true, 'quality_score' => 10],
        ['is_valid' => false, 'evaluation_failed' => true, 'quality_score' => 10],
        ['is_valid' => true,  'evaluation_failed' => false, 'quality_score' => 80],
    ];
    $result = (new TaskPerformanceEvaluator())->evaluate(makeBatch($rows));

    expect($result->score)->toBeLessThan(70.0);
});

// --- ConversationQualityEvaluator ---

test('ConversationQualityEvaluator returns not_applicable when conversation_enabled is false', function () {
    $rows = [['payload' => ['question' => 'hello']], ['payload' => ['question' => 'world']]];
    $result = (new ConversationQualityEvaluator())->evaluate(makeBatch($rows));

    expect($result->evaluator)->toBe('conversation_quality')
        ->and($result->status)->toBe('not_applicable')
        ->and($result->passed)->toBeTrue();
});

test('ConversationQualityEvaluator scores well-structured conversations highly', function () {
    $rows = array_fill(0, 4, [
        'messages' => [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there'],
        ],
        'turn_count' => 2,
        'refined_by' => null,
    ]);
    $result = (new ConversationQualityEvaluator())->evaluate(makeBatch($rows, ['conversation_enabled' => true]));

    expect($result->passed)->toBeTrue()
        ->and($result->details['conversational_rows'])->toBe(4)
        ->and($result->details['well_structured_rows'])->toBe(4);
});

// --- DistributionDriftEvaluator ---

test('DistributionDriftEvaluator scores high when no duplicates and no drift', function () {
    $rows = array_fill(0, 5, ['is_duplicate' => false, 'payload' => ['a' => 1, 'b' => 2]]);
    $result = (new DistributionDriftEvaluator())->evaluate(makeBatch($rows, ['baseline_duplicate_rate' => 0.0]));

    expect($result->evaluator)->toBe('distribution_drift')
        ->and($result->score)->toBeGreaterThanOrEqual(90.0)
        ->and($result->passed)->toBeTrue();
});

test('DistributionDriftEvaluator penalises high duplicate rate', function () {
    $rows = array_fill(0, 5, ['is_duplicate' => true, 'payload' => ['a' => 1]]);
    $result = (new DistributionDriftEvaluator())->evaluate(makeBatch($rows));

    expect($result->score)->toBeLessThan(80.0)
        ->and($result->details['duplicate_rate'])->toBe(1.0);
});

test('DistributionDriftEvaluator detects drift from baseline', function () {
    $rows = array_fill(0, 10, ['is_duplicate' => false, 'payload' => ['x' => 1]]);
    $result = (new DistributionDriftEvaluator())->evaluate(makeBatch($rows, ['baseline_duplicate_rate' => 0.5]));

    expect($result->details['duplicate_drift'])->toBe(0.5);
});

// --- NegativeRatioEvaluator ---

test('NegativeRatioEvaluator scores 100 when ratio is within range', function () {
    $rows = array_merge(
        array_fill(0, 8, ['payload' => ['q' => 'normal']]),
        array_fill(0, 2, ['failure_reason' => 'wrong_tool', 'payload' => []]),
    );
    $result = (new NegativeRatioEvaluator(0.05, 0.40))->evaluate(makeBatch($rows, ['negative_example_ratio' => 10]));

    expect($result->evaluator)->toBe('negative_ratio')
        ->and($result->score)->toBe(100.0)
        ->and($result->passed)->toBeTrue()
        ->and($result->details['within_range'])->toBeTrue();
});

test('NegativeRatioEvaluator scores low when no negatives and min ratio required', function () {
    $rows = array_fill(0, 10, ['payload' => ['q' => 'normal']]);
    $result = (new NegativeRatioEvaluator(0.20, 0.40))->evaluate(makeBatch($rows, ['negative_example_ratio' => 20]));

    expect($result->score)->toBeLessThan(60.0)
        ->and($result->details['within_range'])->toBeFalse();
});

test('NegativeRatioEvaluator respects metadata overrides for ratio bounds', function () {
    $rows = array_fill(0, 10, ['failure_reason' => 'bad', 'payload' => []]);
    $result = (new NegativeRatioEvaluator())->evaluate(makeBatch($rows, [
        'negative_example_ratio' => 10,
        'min_negative_ratio' => 0.9,
        'max_negative_ratio' => 1.0,
    ]));

    expect($result->details['within_range'])->toBeTrue();
});

// --- EdgeCaseEvaluator ---

test('EdgeCaseEvaluator returns passing score for rows without similarity data', function () {
    $rows = array_fill(0, 5, ['payload' => ['x' => 1]]);
    $result = (new EdgeCaseEvaluator())->evaluate(makeBatch($rows));

    expect($result->evaluator)->toBe('edge_case')
        ->and($result->passed)->toBeTrue();
});

test('EdgeCaseEvaluator rewards low similarity scores as edge cases', function () {
    $rows = array_fill(0, 5, ['source_similarity_score' => 0.1, 'critic_feedback' => 'needs work']);
    $result = (new EdgeCaseEvaluator())->evaluate(makeBatch($rows));

    expect($result->details['edge_case_rate'])->toBe(1.0)
        ->and($result->details['avg_source_similarity'])->toBe(0.1);
});

// --- EvaluationPipeline ---

test('EvaluationPipeline aggregates results from all evaluators', function () {
    $rows = array_fill(0, 5, [
        'is_valid' => true,
        'evaluation_failed' => false,
        'quality_score' => 85,
        'is_duplicate' => false,
        'payload' => ['a' => 1, 'b' => 2],
    ]);

    $pipeline = new EvaluationPipeline([
        new TaskPerformanceEvaluator(),
        new ConversationQualityEvaluator(),
        new DistributionDriftEvaluator(),
        new NegativeRatioEvaluator(),
        new EdgeCaseEvaluator(),
    ]);

    $result = $pipeline->run(makeBatch($rows));

    expect($result)->toBeInstanceOf(BatchEvaluationResultDTO::class)
        ->and($result->evaluatorResults)->toHaveKeys([
            'task_performance',
            'conversation_quality',
            'distribution_drift',
            'negative_ratio',
            'edge_case',
        ])
        ->and($result->metadata['total_evaluators'])->toBe(5)
        ->and($result->metadata)->toHaveKey('applicable_evaluators')
        ->and($result->metadata)->toHaveKey('quality_band');
});

test('EvaluationPipeline rejects batch when overall score is below threshold', function () {
    $rows = array_fill(0, 5, [
        'is_valid' => false,
        'evaluation_failed' => true,
        'quality_score' => 5,
        'is_duplicate' => true,
    ]);

    $pipeline = new EvaluationPipeline([
        new TaskPerformanceEvaluator(),
        new DistributionDriftEvaluator(),
    ]);

    $result = $pipeline->run(makeBatch($rows));

    expect($result->passed)->toBeFalse()
        ->and($result->verdict)->toContain('rejected');
});

// --- DatasetEvaluationService ---

test('DatasetEvaluationService evaluates a version and persists a report', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create(['user_id' => $user->id, 'ai_provider_id' => $provider->id]);
    $version = DatasetVersion::factory()->create(['dataset_project_id' => $project->id, 'ai_provider_id' => $provider->id]);

    DatasetRow::factory()->count(5)->create([
        'dataset_version_id' => $version->id,
        'is_valid' => true,
        'evaluation_failed' => false,
        'quality_score' => 80,
        'is_duplicate' => false,
    ]);

    $service = new DatasetEvaluationService();
    $report = $service->evaluate($version);

    expect($report)->toBeInstanceOf(DatasetEvaluationReport::class)
        ->and($report->dataset_version_id)->toBe($version->id)
        ->and($report->overall_score)->toBeFloat()
        ->and($report->evaluator_scores)->toHaveKeys([
            'task_performance',
            'conversation_quality',
            'distribution_drift',
            'negative_ratio',
            'edge_case',
        ]);

    $this->assertDatabaseHas('dataset_evaluation_reports', [
        'dataset_version_id' => $version->id,
    ]);
});

test('DatasetEvaluationService compare returns baseline, candidate and improved flag', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create(['user_id' => $user->id, 'ai_provider_id' => $provider->id]);

    $baseline = DatasetVersion::factory()->create(['dataset_project_id' => $project->id, 'ai_provider_id' => $provider->id]);
    $candidate = DatasetVersion::factory()->create(['dataset_project_id' => $project->id, 'ai_provider_id' => $provider->id]);

    DatasetRow::factory()->count(3)->create(['dataset_version_id' => $baseline->id, 'is_valid' => true, 'is_duplicate' => false]);
    DatasetRow::factory()->count(3)->create(['dataset_version_id' => $candidate->id, 'is_valid' => true, 'is_duplicate' => false]);

    $service = new DatasetEvaluationService();
    $comparison = $service->compare($baseline, $candidate);

    expect($comparison)->toHaveKeys(['baseline', 'candidate', 'improved'])
        ->and($comparison['baseline'])->toBeInstanceOf(DatasetEvaluationReport::class)
        ->and($comparison['candidate'])->toBeInstanceOf(DatasetEvaluationReport::class)
        ->and($comparison['improved'])->toBeBool();
});

test('DatasetEvaluationService release creates a DatasetReleaseVersion for passing report', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create(['user_id' => $user->id, 'ai_provider_id' => $provider->id]);
    $version = DatasetVersion::factory()->create(['dataset_project_id' => $project->id, 'ai_provider_id' => $provider->id]);

    $report = DatasetEvaluationReport::create([
        'dataset_version_id' => $version->id,
        'overall_score' => 88.0,
        'passed' => true,
        'verdict' => 'Dataset passed all evaluation criteria and is approved for release.',
        'evaluator_scores' => [],
        'metadata' => [],
        'duplicate_rate' => 0.0,
    ]);

    $service = new DatasetEvaluationService();
    $release = $service->release($report, 'v1.0.0', 'First stable release');

    expect($release)->toBeInstanceOf(DatasetReleaseVersion::class)
        ->and($release->release_tag)->toBe('v1.0.0')
        ->and($release->notes)->toBe('First stable release')
        ->and($release->dataset_version_id)->toBe($version->id);

    $this->assertDatabaseHas('dataset_release_versions', [
        'release_tag' => 'v1.0.0',
        'dataset_version_id' => $version->id,
    ]);
});

test('DatasetEvaluationService release throws when report did not pass', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create(['user_id' => $user->id, 'ai_provider_id' => $provider->id]);
    $version = DatasetVersion::factory()->create(['dataset_project_id' => $project->id, 'ai_provider_id' => $provider->id]);

    $report = DatasetEvaluationReport::create([
        'dataset_version_id' => $version->id,
        'overall_score' => 30.0,
        'passed' => false,
        'verdict' => 'Dataset rejected.',
        'evaluator_scores' => [],
        'metadata' => [],
        'duplicate_rate' => 0.0,
    ]);

    $service = new DatasetEvaluationService();

    expect(fn () => $service->release($report, 'v0.1.0'))->toThrow(RuntimeException::class);
});
