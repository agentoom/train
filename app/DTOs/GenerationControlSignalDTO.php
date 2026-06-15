<?php

namespace App\DTOs;

use App\Enums\SignalPriority;
use App\Enums\SignalType;
use App\Enums\TargetComponent;

/**
 * Structured schema for a single generation control signal (Phase 1.6).
 *
 * Every signal must be traceable to a measurable evaluator metric.
 */
final class GenerationControlSignalDTO
{
    public function __construct(
        public readonly SignalType $signalType,
        public readonly float $strength,
        public readonly TargetComponent $targetComponent,
        public readonly SignalPriority $priority,
        public readonly string $explanation,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->signalType->value,
            'strength' => $this->strength,
            'target_component' => $this->targetComponent->value,
            'priority' => $this->priority->value,
            'explanation' => $this->explanation,
        ];
    }
}
