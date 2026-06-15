<?php

use App\Enums\ExportFormat;
use App\Models\DatasetProject;
use App\Models\DatasetRow;
use App\Models\DatasetVersion;
use App\Services\Dataset\DatasetExportService;
use App\Services\Export\ExportPresetTransformer;

// ─── ExportFormat enum ────────────────────────────────────────────────────────

test('ExportFormat preset cases are recognised by isPreset()', function () {
    expect(ExportFormat::OpenAI->isPreset())->toBeTrue()
        ->and(ExportFormat::Anthropic->isPreset())->toBeTrue()
        ->and(ExportFormat::HuggingFace->isPreset())->toBeTrue()
        ->and(ExportFormat::Axolotl->isPreset())->toBeTrue()
        ->and(ExportFormat::Unsloth->isPreset())->toBeTrue()
        ->and(ExportFormat::LlamaFactory->isPreset())->toBeTrue()
        ->and(ExportFormat::GenericToolCalling->isPreset())->toBeTrue()
        ->and(ExportFormat::Json->isPreset())->toBeFalse()
        ->and(ExportFormat::Jsonl->isPreset())->toBeFalse()
        ->and(ExportFormat::Csv->isPreset())->toBeFalse();
});

test('ExportFormat preset cases all use jsonl extension', function () {
    foreach ([
        ExportFormat::OpenAI,
        ExportFormat::Anthropic,
        ExportFormat::HuggingFace,
        ExportFormat::Axolotl,
        ExportFormat::Unsloth,
        ExportFormat::LlamaFactory,
        ExportFormat::GenericToolCalling,
    ] as $format) {
        expect($format->extension())->toBe('jsonl');
    }
});

test('ExportFormat tryFrom resolves all preset slugs', function () {
    expect(ExportFormat::tryFrom('openai'))->toBe(ExportFormat::OpenAI)
        ->and(ExportFormat::tryFrom('anthropic'))->toBe(ExportFormat::Anthropic)
        ->and(ExportFormat::tryFrom('huggingface'))->toBe(ExportFormat::HuggingFace)
        ->and(ExportFormat::tryFrom('axolotl'))->toBe(ExportFormat::Axolotl)
        ->and(ExportFormat::tryFrom('unsloth'))->toBe(ExportFormat::Unsloth)
        ->and(ExportFormat::tryFrom('llamafactory'))->toBe(ExportFormat::LlamaFactory)
        ->and(ExportFormat::tryFrom('tool-calling'))->toBe(ExportFormat::GenericToolCalling);
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeRow(array $payload, ?array $messages = null, ?string $expectedBehavior = null, ?string $failureReason = null): DatasetRow
{
    $row = new DatasetRow;
    $row->payload = $payload;
    $row->messages = $messages;
    $row->expected_behavior = $expectedBehavior;
    $row->failure_reason = $failureReason;

    return $row;
}

// ─── OpenAI preset ────────────────────────────────────────────────────────────

test('OpenAI preset transforms QA row into messages format', function () {
    $transformer = app(ExportPresetTransformer::class);
    $row = makeRow(['user' => 'What is PHP?', 'assistant' => 'A scripting language.']);

    $result = $transformer->transform($row, ExportFormat::OpenAI);

    expect($result)->toHaveKey('messages')
        ->and($result['messages'])->toHaveCount(2)
        ->and($result['messages'][0])->toBe(['role' => 'user', 'content' => 'What is PHP?'])
        ->and($result['messages'][1])->toBe(['role' => 'assistant', 'content' => 'A scripting language.']);
});

test('OpenAI preset uses conversation messages when present', function () {
    $transformer = app(ExportPresetTransformer::class);
    $messages = [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi there'],
    ];
    $row = makeRow([], $messages);

    $result = $transformer->transform($row, ExportFormat::OpenAI);

    expect($result)->toBe(['messages' => $messages]);
});

test('OpenAI preset includes system message when present in payload', function () {
    $transformer = app(ExportPresetTransformer::class);
    $row = makeRow(['system' => 'You are helpful.', 'user' => 'Hi', 'assistant' => 'Hello!']);

    $result = $transformer->transform($row, ExportFormat::OpenAI);

    expect($result['messages'][0])->toBe(['role' => 'system', 'content' => 'You are helpful.'])
        ->and($result['messages'])->toHaveCount(3);
});

// ─── Anthropic preset ─────────────────────────────────────────────────────────

test('Anthropic preset transforms QA row without system field', function () {
    $transformer = app(ExportPresetTransformer::class);
    $row = makeRow(['user' => 'Hello', 'assistant' => 'Hi']);

    $result = $transformer->transform($row, ExportFormat::Anthropic);

    expect($result)->toHaveKey('messages')
        ->and($result)->not->toHaveKey('system');
});

test('Anthropic preset extracts system field separately', function () {
    $transformer = app(ExportPresetTransformer::class);
    $row = makeRow(['system' => 'Be concise.', 'user' => 'Hello', 'assistant' => 'Hi']);

    $result = $transformer->transform($row, ExportFormat::Anthropic);

    expect($result['system'])->toBe('Be concise.')
        ->and($result['messages'])->toHaveCount(2);
});

test('Anthropic preset maps conversation messages correctly', function () {
    $transformer = app(ExportPresetTransformer::class);
    $messages = [
        ['role' => 'user', 'content' => 'Question'],
        ['role' => 'assistant', 'content' => 'Answer'],
    ];
    $row = makeRow([], $messages);

    $result = $transformer->transform($row, ExportFormat::Anthropic);

    expect($result['messages'][0]['role'])->toBe('user')
        ->and($result['messages'][1]['role'])->toBe('assistant');
});

// ─── HuggingFace preset ───────────────────────────────────────────────────────

test('HuggingFace preset produces conversations with from/value keys', function () {
    $transformer = app(ExportPresetTransformer::class);
    $row = makeRow(['user' => 'Hi', 'assistant' => 'Hello']);

    $result = $transformer->transform($row, ExportFormat::HuggingFace);

    expect($result)->toHaveKey('conversations')
        ->and($result['conversations'][0])->toHaveKeys(['from', 'value'])
        ->and($result['conversations'][0]['from'])->toBe('human')
        ->and($result['conversations'][1]['from'])->toBe('gpt');
});

test('HuggingFace preset maps system role correctly', function () {
    $transformer = app(ExportPresetTransformer::class);
    $messages = [
        ['role' => 'system', 'content' => 'You are helpful.'],
        ['role' => 'user', 'content' => 'Hi'],
        ['role' => 'assistant', 'content' => 'Hello'],
    ];
    $row = makeRow([], $messages);

    $result = $transformer->transform($row, ExportFormat::HuggingFace);

    expect($result['conversations'][0]['from'])->toBe('system')
        ->and($result['conversations'][1]['from'])->toBe('human')
        ->and($result['conversations'][2]['from'])->toBe('gpt');
});

// ─── Axolotl preset ───────────────────────────────────────────────────────────

test('Axolotl preset includes system field and conversations', function () {
    $transformer = app(ExportPresetTransformer::class);
    $row = makeRow(['system' => 'Be helpful.', 'user' => 'Hi', 'assistant' => 'Hello']);

    $result = $transformer->transform($row, ExportFormat::Axolotl);

    expect($result)->toHaveKey('system')
        ->and($result)->toHaveKey('conversations')
        ->and($result['system'])->toBe('Be helpful.');
});

// ─── Unsloth preset ───────────────────────────────────────────────────────────

test('Unsloth preset produces same output as OpenAI preset', function () {
    $transformer = app(ExportPresetTransformer::class);
    $row = makeRow(['user' => 'What is AI?', 'assistant' => 'Artificial Intelligence.']);

    $openai  = $transformer->transform($row, ExportFormat::OpenAI);
    $unsloth = $transformer->transform($row, ExportFormat::Unsloth);

    expect($unsloth)->toBe($openai);
});

// ─── LlamaFactory preset ──────────────────────────────────────────────────────

test('LlamaFactory preset produces alpaca-style output for QA row', function () {
    $transformer = app(ExportPresetTransformer::class);
    $row = makeRow(['instruction' => 'Translate to French.', 'input' => 'Hello', 'output' => 'Bonjour']);

    $result = $transformer->transform($row, ExportFormat::LlamaFactory);

    expect($result)->toHaveKey('instruction')
        ->and($result)->toHaveKey('input')
        ->and($result)->toHaveKey('output')
        ->and($result['output'])->toBe('Bonjour');
});

test('LlamaFactory preset converts multi-turn conversation to history format', function () {
    $transformer = app(ExportPresetTransformer::class);
    $messages = [
        ['role' => 'user', 'content' => 'First question'],
        ['role' => 'assistant', 'content' => 'First answer'],
        ['role' => 'user', 'content' => 'Second question'],
        ['role' => 'assistant', 'content' => 'Second answer'],
    ];
    $row = makeRow([], $messages);

    $result = $transformer->transform($row, ExportFormat::LlamaFactory);

    expect($result)->toHaveKey('history')
        ->and($result['history'])->toHaveCount(1)
        ->and($result['history'][0])->toBe(['First question', 'First answer'])
        ->and($result['input'])->toBe('Second question')
        ->and($result['output'])->toBe('Second answer');
});

// ─── Generic Tool Calling preset ─────────────────────────────────────────────

test('GenericToolCalling preset includes tools and messages', function () {
    $transformer = app(ExportPresetTransformer::class);
    $row = makeRow([
        'tools'      => [['name' => 'get_weather', 'description' => 'Get weather']],
        'user'       => 'What is the weather?',
        'tool_calls' => [['name' => 'get_weather', 'arguments' => ['city' => 'Paris']]],
    ]);

    $result = $transformer->transform($row, ExportFormat::GenericToolCalling);

    expect($result)->toHaveKey('tools')
        ->and($result)->toHaveKey('messages');
});

test('GenericToolCalling preset includes expected_behavior for negative examples', function () {
    $transformer = app(ExportPresetTransformer::class);
    $row = makeRow(
        ['user' => 'Do something', 'assistant' => 'Wrong response'],
        null,
        'incorrect',
        'wrong_tool_selection',
    );

    $result = $transformer->transform($row, ExportFormat::GenericToolCalling);

    expect($result['expected_behavior'])->toBe('incorrect')
        ->and($result['failure_reason'])->toBe('wrong_tool_selection');
});

// ─── DatasetExportService integration ────────────────────────────────────────

test('DatasetExportService returns a StreamedResponse with correct headers for preset', function () {
    $project = DatasetProject::factory()->create();
    $version = DatasetVersion::factory()->create(['dataset_project_id' => $project->id]);

    $service  = app(DatasetExportService::class);
    $response = $service->export($version, ExportFormat::OpenAI);

    expect($response->headers->get('Content-Type'))->toContain('application/jsonl')
        ->and($response->headers->get('Content-Disposition'))->toContain('.jsonl');
});

test('DatasetExportService transformer produces correct output for saved rows', function () {
    $project = DatasetProject::factory()->create();
    $version = DatasetVersion::factory()->create(['dataset_project_id' => $project->id]);
    $batch   = \App\Models\GenerationBatch::factory()->create(['dataset_version_id' => $version->id]);

    $row = DatasetRow::factory()->create([
        'dataset_version_id'  => $version->id,
        'generation_batch_id' => $batch->id,
        'row_index'           => 0,
        'payload'             => ['user' => 'Hello', 'assistant' => 'Hi'],
        'is_valid'            => true,
        'is_duplicate'        => false,
    ]);

    $transformer = app(ExportPresetTransformer::class);
    $result      = $transformer->transform($row->fresh(), ExportFormat::OpenAI);

    expect($result)->toHaveKey('messages')
        ->and($result['messages'][0]['role'])->toBe('user')
        ->and($result['messages'][0]['content'])->toBe('Hello');
});

test('DatasetExportService transformer skips duplicate rows correctly', function () {
    $project = DatasetProject::factory()->create();
    $version = DatasetVersion::factory()->create(['dataset_project_id' => $project->id]);
    $batch   = \App\Models\GenerationBatch::factory()->create(['dataset_version_id' => $version->id]);

    DatasetRow::factory()->create([
        'dataset_version_id'  => $version->id,
        'generation_batch_id' => $batch->id,
        'row_index'           => 0,
        'payload'             => ['user' => 'Hello', 'assistant' => 'Hi'],
        'is_valid'            => true,
        'is_duplicate'        => false,
    ]);

    DatasetRow::factory()->create([
        'dataset_version_id'  => $version->id,
        'generation_batch_id' => $batch->id,
        'row_index'           => 1,
        'payload'             => ['user' => 'Hello', 'assistant' => 'Hi'],
        'is_valid'            => true,
        'is_duplicate'        => true,
    ]);

    $transformer = app(ExportPresetTransformer::class);

    $rows = DatasetRow::where('dataset_version_id', $version->id)
        ->where('is_valid', true)
        ->where('is_duplicate', false)
        ->get();

    expect($rows)->toHaveCount(1);

    $result = $transformer->transform($rows->first(), ExportFormat::HuggingFace);
    expect($result)->toHaveKey('conversations');
});
