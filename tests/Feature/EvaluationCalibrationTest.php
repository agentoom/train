<?php

use App\DTOs\DatasetBatchDTO;
use App\Models\AIProvider;
use App\Models\DatasetProject;
use App\Models\DatasetVersion;
use App\Models\User;
use App\Services\DatasetEvaluation\EvaluationPipeline;
use App\Services\DatasetEvaluation\Evaluators\ConversationQualityEvaluator;
use App\Services\DatasetEvaluation\Evaluators\DistributionDriftEvaluator;
use App\Services\DatasetEvaluation\Evaluators\EdgeCaseEvaluator;
use App\Services\DatasetEvaluation\Evaluators\NegativeRatioEvaluator;
use App\Services\DatasetEvaluation\Evaluators\TaskPerformanceEvaluator;
use Illuminate\Support\Collection;

/**
 * Calibration benchmark tests.
 *
 * These tests ensure the evaluation system scores datasets within expected
 * quality bands and prevent scoring regressions.
 *
 * Bands:
 *   Poor dataset      → 0–45
 *   Decent dataset    → 60–89
 *   Excellent dataset → 85–100
 */

// --- Helpers ---

function makeCalibrationBatch(array $rows = [], array $metadata = []): DatasetBatchDTO
{
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);
    $project = DatasetProject::factory()->create(['user_id' => $user->id, 'ai_provider_id' => $provider->id]);
    $version = DatasetVersion::factory()->create(['dataset_project_id' => $project->id, 'ai_provider_id' => $provider->id]);

    return new DatasetBatchDTO(
        version: $version,
        rows: Collection::make($rows),
        schema: null,
        metadata: $metadata,
    );
}

function makeFullPipeline(): EvaluationPipeline
{
    return new EvaluationPipeline([
        new TaskPerformanceEvaluator(),
        new ConversationQualityEvaluator(),
        new DistributionDriftEvaluator(),
        new NegativeRatioEvaluator(),
        new EdgeCaseEvaluator(),
    ]);
}

// --- Poor dataset fixture ---
// Contains: many duplicates, invalid rows, evaluation failures, low quality scores

test('calibration: poor dataset scores between 25 and 45', function () {
    $rows = array_merge(
        // 60% duplicates
        array_fill(0, 6, [
            'is_valid' => false,
            'evaluation_failed' => true,
            'quality_score' => 5,
            'is_duplicate' => true,
            'payload' => ['text' => 'repeated content'],
        ]),
        // 40% invalid, no quality score
        array_fill(0, 4, [
            'is_valid' => false,
            'evaluation_failed' => true,
            'quality_score' => 10,
            'is_duplicate' => false,
            'payload' => ['text' => 'bad row'],
        ]),
    );

    $result = makeFullPipeline()->run(makeCalibrationBatch($rows, [
        'baseline_duplicate_rate' => 0.0,
        'negative_example_ratio' => 0,
        'conversation_enabled' => false,
    ]));

    expect($result->overallScore)->toBeGreaterThanOrEqual(0.0)
        ->and($result->overallScore)->toBeLessThanOrEqual(45.0)
        ->and($result->passed)->toBeFalse()
        ->and($result->metadata['quality_band'])->toBeIn(['Poor', 'Weak']);
});

// --- Decent dataset fixture ---
// Realistic usable quality: mostly valid, low duplicates, no special features

test('calibration: decent dataset scores between 60 and 75', function () {
    $rows = array_merge(
        // 85% valid, no failures, decent quality
        array_fill(0, 17, [
            'is_valid' => true,
            'evaluation_failed' => false,
            'quality_score' => 65,
            'is_duplicate' => false,
            'payload' => ['instruction' => 'Do task A', 'output' => 'Result A'],
        ]),
        // 15% invalid
        array_fill(0, 3, [
            'is_valid' => false,
            'evaluation_failed' => false,
            'quality_score' => 40,
            'is_duplicate' => false,
            'payload' => ['instruction' => 'Do task B', 'output' => 'Result B'],
        ]),
    );

    $result = makeFullPipeline()->run(makeCalibrationBatch($rows, [
        'baseline_duplicate_rate' => 0.0,
        'negative_example_ratio' => 0,
        'conversation_enabled' => false,
    ]));

    expect($result->overallScore)->toBeGreaterThanOrEqual(60.0)
        ->and($result->overallScore)->toBeLessThanOrEqual(89.0)
        ->and($result->passed)->toBeTrue()
        ->and($result->metadata['quality_band'])->toBeIn(['Good', 'Strong']);
});

// --- Excellent dataset fixture ---
// High diversity, strong quality, good coverage, no duplicates

test('calibration: excellent dataset scores between 85 and 95', function () {
    $rows = array_fill(0, 20, [
        'is_valid' => true,
        'evaluation_failed' => false,
        'quality_score' => 92,
        'is_duplicate' => false,
        'source_similarity_score' => 0.2,
        'critic_feedback' => 'Looks good',
        'payload' => ['instruction' => 'Complex task', 'output' => 'High quality result', 'context' => 'Rich context'],
    ]);

    $result = makeFullPipeline()->run(makeCalibrationBatch($rows, [
        'baseline_duplicate_rate' => 0.0,
        'negative_example_ratio' => 0,
        'conversation_enabled' => false,
    ]));

    expect($result->overallScore)->toBeGreaterThanOrEqual(85.0)
        ->and($result->overallScore)->toBeLessThanOrEqual(100.0)
        ->and($result->passed)->toBeTrue()
        ->and($result->metadata['quality_band'])->toBe('Excellent');
});

// --- Applicability tests ---

test('calibration: classification dataset skips edge_case evaluator', function () {
    $rows = array_fill(0, 10, [
        'is_valid' => true,
        'evaluation_failed' => false,
        'quality_score' => 80,
        'is_duplicate' => false,
        'payload' => ['label' => 'positive', 'text' => 'Great product'],
    ]);

    $result = makeFullPipeline()->run(makeCalibrationBatch($rows, [
        'dataset_type' => 'classification',
        'negative_example_ratio' => 0,
        'conversation_enabled' => false,
    ]));

    expect($result->evaluatorResults['edge_case']->status)->toBe('not_applicable')
        ->and($result->metadata['skipped_evaluators'])->toHaveKey('edge_case');
});

test('calibration: dataset with negative_example_ratio=0 skips negative_ratio evaluator', function () {
    $rows = array_fill(0, 10, [
        'is_valid' => true,
        'evaluation_failed' => false,
        'quality_score' => 80,
        'is_duplicate' => false,
        'payload' => ['q' => 'question', 'a' => 'answer'],
    ]);

    $result = makeFullPipeline()->run(makeCalibrationBatch($rows, [
        'negative_example_ratio' => 0,
        'conversation_enabled' => false,
    ]));

    expect($result->evaluatorResults['negative_ratio']->status)->toBe('not_applicable')
        ->and($result->metadata['skipped_evaluators'])->toHaveKey('negative_ratio');
});

test('calibration: conversation dataset runs conversation_quality evaluator', function () {
    $rows = array_fill(0, 5, [
        'is_valid' => true,
        'evaluation_failed' => false,
        'quality_score' => 80,
        'is_duplicate' => false,
        'messages' => [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there'],
        ],
        'turn_count' => 2,
        'payload' => [],
    ]);

    $result = makeFullPipeline()->run(makeCalibrationBatch($rows, [
        'conversation_enabled' => true,
        'negative_example_ratio' => 0,
    ]));

    expect($result->evaluatorResults['conversation_quality']->status)->toBe('applicable')
        ->and($result->evaluatorResults['conversation_quality']->score)->toBeGreaterThan(0.0);
});

// --- Weighted scoring tests ---

test('calibration: non-critical evaluator failure does not hard-fail the dataset', function () {
    // Good task performance and distribution, but conversation quality is poor (single-message)
    $rows = array_fill(0, 10, [
        'is_valid' => true,
        'evaluation_failed' => false,
        'quality_score' => 85,
        'is_duplicate' => false,
        'messages' => [['role' => 'user', 'content' => 'x']], // single-message, poor structure
        'payload' => ['a' => 1],
    ]);

    $result = makeFullPipeline()->run(makeCalibrationBatch($rows, [
        'conversation_enabled' => true,
        'negative_example_ratio' => 0,
        'baseline_duplicate_rate' => 0.0,
    ]));

    // conversation_quality may fail but dataset should still pass on overall merit
    expect($result->passed)->toBeTrue();
});

test('calibration: critical evaluator below floor hard-fails the dataset', function () {
    // All rows invalid — task_performance will be very low
    $rows = array_fill(0, 10, [
        'is_valid' => false,
        'evaluation_failed' => true,
        'quality_score' => 2,
        'is_duplicate' => false,
        'payload' => ['a' => 1],
    ]);

    $result = makeFullPipeline()->run(makeCalibrationBatch($rows, [
        'negative_example_ratio' => 0,
        'conversation_enabled' => false,
        'baseline_duplicate_rate' => 0.0,
    ]));

    expect($result->passed)->toBeFalse()
        ->and($result->verdict)->toContain('rejected');
});

// --- Quality band tests ---

test('calibration: quality bands are correctly assigned', function () {
    $pipeline = makeFullPipeline();

    // Excellent: all valid, high quality, no duplicates, good diversity
    $excellentRows = array_fill(0, 10, [
        'is_valid' => true, 'evaluation_failed' => false, 'quality_score' => 95,
        'is_duplicate' => false, 'source_similarity_score' => 0.15,
        'payload' => ['a' => 1, 'b' => 2],
    ]);
    $excellent = $pipeline->run(makeCalibrationBatch($excellentRows, ['negative_example_ratio' => 0, 'conversation_enabled' => false]));
    expect($excellent->metadata['quality_band'])->toBe('Excellent');

    // Poor: all invalid, all duplicates
    $poorRows = array_fill(0, 10, [
        'is_valid' => false, 'evaluation_failed' => true, 'quality_score' => 3,
        'is_duplicate' => true, 'payload' => ['a' => 1],
    ]);
    $poor = $pipeline->run(makeCalibrationBatch($poorRows, ['negative_example_ratio' => 0, 'conversation_enabled' => false]));
    expect($poor->metadata['quality_band'])->toBeIn(['Poor', 'Weak']);
});
