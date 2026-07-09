<?php

use App\DTOs\GenerationResultDTO;
use App\Enums\BatchStatus;
use App\Jobs\GenerateDatasetBatchJob;
use App\Models\AIProvider;
use App\Models\DatasetProject;
use App\Models\DatasetRow;
use App\Models\DatasetVersion;
use App\Models\GenerationBatch;
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

// ─── ConversationPromptBuilderService ────────────────────────────────────────

test('ConversationPromptBuilderService builds prompt with messages format instructions', function () {
    $project = DatasetProject::factory()->create([
        'conversation_enabled' => true,
        'conversation_type' => 'assistant_chat',
        'min_turns' => 2,
        'max_turns' => 5,
        'branching_enabled' => false,
    ]);

    $config = app(ConversationPromptBuilderService::class)->build($project, recordCount: 3);

    expect($config->userPrompt)
        ->toContain('Generate EXACTLY 3')
        ->toContain('"messages"')
        ->toContain('"role"')
        ->toContain('"content"')
        ->toContain('between 2 and 5 turns');
});

test('ConversationPromptBuilderService includes tool usage instructions for tool_usage type', function () {
    $project = DatasetProject::factory()->create([
        'conversation_enabled' => true,
        'conversation_type' => 'tool_usage',
        'min_turns' => 2,
        'max_turns' => 4,
        'branching_enabled' => false,
    ]);

    $config = app(ConversationPromptBuilderService::class)->build($project, recordCount: 1);

    expect($config->userPrompt)
        ->toContain('tool_result')
        ->toContain('tool_call')
        ->toContain('Tool Usage');
});

test('ConversationPromptBuilderService includes branching instructions when enabled', function () {
    $project = DatasetProject::factory()->create([
        'conversation_enabled' => true,
        'conversation_type' => 'assistant_chat',
        'min_turns' => 2,
        'max_turns' => 6,
        'branching_enabled' => true,
    ]);

    $config = app(ConversationPromptBuilderService::class)->build($project, recordCount: 2);

    expect($config->userPrompt)->toContain('BRANCHING');
});

test('ConversationPromptBuilderService does not include branching when disabled', function () {
    $project = DatasetProject::factory()->create([
        'conversation_enabled' => true,
        'conversation_type' => 'assistant_chat',
        'min_turns' => 2,
        'max_turns' => 6,
        'branching_enabled' => false,
    ]);

    $config = app(ConversationPromptBuilderService::class)->build($project, recordCount: 2);

    expect($config->userPrompt)->not->toContain('BRANCHING');
});

test('ConversationPromptBuilderService buildFromVersion uses version snapshot', function () {
    $project = DatasetProject::factory()->create([
        'conversation_enabled' => true,
        'conversation_type' => 'support_workflow',
        'min_turns' => 3,
        'max_turns' => 8,
        'branching_enabled' => false,
    ]);

    $version = DatasetVersion::factory()->create([
        'dataset_project_id' => $project->id,
        'system_prompt_snapshot' => 'You are a support agent.',
        'record_count' => 5,
    ]);

    $config = app(ConversationPromptBuilderService::class)->buildFromVersion($version, recordCount: 5);

    expect($config->systemPrompt)->toBe('You are a support agent.')
        ->and($config->userPrompt)->toContain('Support Workflow')
        ->and($config->userPrompt)->toContain('between 3 and 8 turns');
});

// ─── GenerateDatasetBatchJob conversation mode ────────────────────────────────

function runConversationJob(GenerationBatch $batch, array $rows): void
{
    $fakeResult = new GenerationResultDTO(
        rows: $rows,
        promptTokens: 100,
        completionTokens: 200,
        totalTokens: 300,
        latencyMs: 500,
    );

    $mockInference = Mockery::mock(InferenceExecutionService::class);
    $mockInference->shouldReceive('execute')->andReturn($fakeResult);
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
}

test('GenerateDatasetBatchJob stores messages and turn_count in conversation mode', function () {
    $project = DatasetProject::factory()->create([
        'conversation_enabled' => true,
        'conversation_type' => 'assistant_chat',
        'min_turns' => 2,
        'max_turns' => 4,
        'ai_provider_id' => AIProvider::factory()->create()->id,
    ]);

    $version = DatasetVersion::factory()->create(['dataset_project_id' => $project->id]);
    $batch = GenerationBatch::factory()->create([
        'dataset_version_id' => $version->id,
        'batch_number' => 1,
        'offset' => 0,
        'limit' => 2,
        'status' => BatchStatus::Pending,
    ]);

    $conversationRows = [
        ['messages' => [
            ['role' => 'user', 'content' => 'Hello, how are you?'],
            ['role' => 'assistant', 'content' => 'I am doing well, thank you!'],
            ['role' => 'user', 'content' => 'Great, can you help me?'],
            ['role' => 'assistant', 'content' => 'Of course!'],
        ]],
        ['messages' => [
            ['role' => 'user', 'content' => 'What is the weather?'],
            ['role' => 'assistant', 'content' => 'I cannot check weather directly.'],
        ]],
    ];

    runConversationJob($batch, $conversationRows);

    $rows = DatasetRow::where('generation_batch_id', $batch->id)->where('is_duplicate', false)->get();

    expect($rows)->toHaveCount(2);
    expect($rows[0]->messages)->toBeArray()->toHaveCount(4);
    expect($rows[0]->turn_count)->toBe(2);
    expect($rows[1]->messages)->toBeArray()->toHaveCount(2);
    expect($rows[1]->turn_count)->toBe(1);
    expect($batch->fresh()->status)->toBe(BatchStatus::Completed);
});

test('GenerateDatasetBatchJob skips conversation rows without messages key', function () {
    $project = DatasetProject::factory()->create([
        'conversation_enabled' => true,
        'ai_provider_id' => AIProvider::factory()->create()->id,
    ]);

    $version = DatasetVersion::factory()->create(['dataset_project_id' => $project->id]);
    $batch = GenerationBatch::factory()->create([
        'dataset_version_id' => $version->id,
        'batch_number' => 1,
        'offset' => 0,
        'limit' => 2,
        'status' => BatchStatus::Pending,
    ]);

    $rows = [
        ['messages' => [
            ['role' => 'user', 'content' => 'Hi'],
            ['role' => 'assistant', 'content' => 'Hello'],
        ]],
        ['text' => 'not a conversation'],
    ];

    runConversationJob($batch, $rows);

    $saved = DatasetRow::where('generation_batch_id', $batch->id)->where('is_duplicate', false)->count();
    expect($saved)->toBe(1);
});

test('GenerateDatasetBatchJob deduplicates conversations by messages hash', function () {
    $project = DatasetProject::factory()->create([
        'conversation_enabled' => true,
        'replacement_generation_enabled' => false,
        'ai_provider_id' => AIProvider::factory()->create()->id,
    ]);

    $version = DatasetVersion::factory()->create(['dataset_project_id' => $project->id]);
    $batch = GenerationBatch::factory()->create([
        'dataset_version_id' => $version->id,
        'batch_number' => 1,
        'offset' => 0,
        'limit' => 2,
        'status' => BatchStatus::Pending,
    ]);

    $sameConversation = ['messages' => [
        ['role' => 'user', 'content' => 'Duplicate question'],
        ['role' => 'assistant', 'content' => 'Duplicate answer'],
    ]];

    runConversationJob($batch, [$sameConversation, $sameConversation]);

    $unique = DatasetRow::where('generation_batch_id', $batch->id)->where('is_duplicate', false)->count();
    $dupes = DatasetRow::where('generation_batch_id', $batch->id)->where('is_duplicate', true)->count();

    expect($unique)->toBe(1)
        ->and($dupes)->toBe(1);
});

test('GenerateDatasetBatchJob uses normal prompt builder when conversation_enabled is false', function () {
    $project = DatasetProject::factory()->create([
        'conversation_enabled' => false,
        'ai_provider_id' => AIProvider::factory()->create()->id,
    ]);

    $version = DatasetVersion::factory()->create(['dataset_project_id' => $project->id]);
    $batch = GenerationBatch::factory()->create([
        'dataset_version_id' => $version->id,
        'batch_number' => 1,
        'offset' => 0,
        'limit' => 2,
        'status' => BatchStatus::Pending,
    ]);

    runConversationJob($batch, [['text' => 'row1'], ['text' => 'row2']]);

    $rows = DatasetRow::where('generation_batch_id', $batch->id)->where('is_duplicate', false)->get();

    expect($rows)->toHaveCount(2);
    expect($rows[0]->messages)->toBeNull();
    expect($rows[0]->turn_count)->toBeNull();
    expect($batch->fresh()->status)->toBe(BatchStatus::Completed);
});
