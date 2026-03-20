<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Workflow;

use Kiboko\Temporal\Retry\ExponentialBackoffPolicy;

/**
 * Politique de retry pour l’exécution **synchrone** d’une activité (PoC in-process).
 *
 * Alignée conceptuellement sur les retries d’activité Temporal ; ici sans historique serveur.
 *
 * Règles (ordre) :
 * - {@see NonRetryableActivityException} → jamais retry
 * - {@see ApplicationActivityFailureException} avec `nonRetryable: true` → pas de retry
 * - {@see ApplicationActivityFailureException} avec `nonRetryable: false` → retry
 * - {@see TransientActivityException} → retry
 * - types listés dans {@see $alsoRetryableExceptionClasses} → retry
 * - sinon → pas de retry
 */
final class ActivityRetryPolicy
{
    private readonly ExponentialBackoffPolicy $backoff;

    /**
     * @param list<class-string<\Throwable>> $alsoRetryableExceptionClasses
     */
    public function __construct(
        public readonly int $maxAttempts = 3,
        int $initialBackoffMicroseconds = 50_000,
        float $backoffMultiplier = 2.0,
        int $maxBackoffMicroseconds = 500_000,
        public readonly array $alsoRetryableExceptionClasses = [],
    ) {
        if ($maxAttempts < 1) {
            throw new \InvalidArgumentException('maxAttempts doit être >= 1.');
        }
        $this->backoff = new ExponentialBackoffPolicy($initialBackoffMicroseconds, $backoffMultiplier, $maxBackoffMicroseconds);
    }

    public function isRetryable(\Throwable $e): bool
    {
        if ($e instanceof NonRetryableActivityException) {
            return false;
        }
        if ($e instanceof ApplicationActivityFailureException) {
            return !$e->nonRetryable;
        }
        if ($e instanceof TransientActivityException) {
            return true;
        }
        foreach ($this->alsoRetryableExceptionClasses as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }

        return false;
    }

    public function backoffMicrosecondsForAttempt(int $attemptIndexZeroBased): int
    {
        return $this->backoff->microsecondsForAttempt($attemptIndexZeroBased);
    }
}
