<?php

namespace App\Support\Retry;

final class ExponentialBackoffStrategy
{
    public function __construct(
        private readonly int $baseDelaySeconds = 1,
        private readonly int $maxDelaySeconds = 60,
        private readonly float $multiplier = 2.0,
        private readonly int $maxAttempts = 3,
    ) {}

    public function getDelay(int $attempt): int
    {
        $delay = (int) ($this->baseDelaySeconds * ($this->multiplier ** ($attempt - 1)));

        return min($delay, $this->maxDelaySeconds);
    }

    public function shouldRetry(int $attempt): bool
    {
        return $attempt <= $this->maxAttempts;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }
}
