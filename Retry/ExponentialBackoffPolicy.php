<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Retry;

/**
 * Backoff exponentiel partagé entre retry gRPC unary et retry HTTP (PSR-18).
 */
final class ExponentialBackoffPolicy
{
    public function __construct(
        public readonly int $initialMicroseconds = 100_000,
        public readonly float $multiplier = 2.0,
        public readonly int $capMicroseconds = 2_000_000,
    ) {
        if ($initialMicroseconds < 0 || $capMicroseconds < 0) {
            throw new \InvalidArgumentException('Backoff en microsecondes doit être >= 0.');
        }
        if ($multiplier < 1.0) {
            throw new \InvalidArgumentException('multiplier doit être >= 1.');
        }
    }

    public function microsecondsForAttempt(int $attemptIndexZeroBased): int
    {
        $raw = (int) round($this->initialMicroseconds * ($this->multiplier ** $attemptIndexZeroBased));

        return min($raw, $this->capMicroseconds);
    }
}
