<?php

namespace App\Enums;

enum AIProviderType: string
{
    case OpenAI = 'openai';
    case Anthropic = 'anthropic';
    case OpenRouter = 'openrouter';
    case Generic = 'generic';

    public function label(): string
    {
        return match ($this) {
            self::OpenAI => 'OpenAI',
            self::Anthropic => 'Anthropic',
            self::OpenRouter => 'OpenRouter',
            self::Generic => 'Generic OpenAI-Compatible',
        };
    }
}
