<?php

namespace App\Enums;

enum ExportFormat: string
{
    case Json = 'json';
    case Jsonl = 'jsonl';
    case Csv = 'csv';

    // Fine-tuning presets (all stream as JSONL)
    case OpenAI = 'openai';
    case Anthropic = 'anthropic';
    case HuggingFace = 'huggingface';
    case Axolotl = 'axolotl';
    case Unsloth = 'unsloth';
    case LlamaFactory = 'llamafactory';
    case GenericToolCalling = 'tool-calling';

    public function mimeType(): string
    {
        return match ($this) {
            self::Json => 'application/json',
            self::Csv => 'text/csv',
            default => 'application/jsonl',
        };
    }

    public function extension(): string
    {
        return match ($this) {
            self::Json => 'json',
            self::Csv => 'csv',
            default => 'jsonl',
        };
    }

    public function isPreset(): bool
    {
        return match ($this) {
            self::OpenAI, self::Anthropic, self::HuggingFace,
            self::Axolotl, self::Unsloth, self::LlamaFactory,
            self::GenericToolCalling => true,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Json => 'JSON',
            self::Jsonl => 'JSONL',
            self::Csv => 'CSV',
            self::OpenAI => 'OpenAI Fine-Tuning',
            self::Anthropic => 'Anthropic',
            self::HuggingFace => 'HuggingFace',
            self::Axolotl => 'Axolotl',
            self::Unsloth => 'Unsloth',
            self::LlamaFactory => 'LlamaFactory',
            self::GenericToolCalling => 'Generic Tool Calling',
        };
    }
}
