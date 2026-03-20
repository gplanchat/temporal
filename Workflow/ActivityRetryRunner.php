<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Workflow;

/**
 * Exécute un callable « activité » avec tentatives et backoff ({@see ActivityRetryPolicy}).
 */
final class ActivityRetryRunner
{
    /**
     * @template T
     *
     * @param callable(): T $once
     *
     * @return T
     */
    public static function runWithRetry(callable $once, ActivityRetryPolicy $policy): mixed
    {
        $last = null;

        for ($attempt = 0; $attempt < $policy->maxAttempts; ++$attempt) {
            try {
                return $once();
            } catch (\Throwable $e) {
                $last = $e;
                if ($attempt + 1 >= $policy->maxAttempts || !$policy->isRetryable($e)) {
                    throw $e;
                }
                usleep($policy->backoffMicrosecondsForAttempt($attempt));
            }
        }

        throw $last ?? new \LogicException('ActivityRetryRunner : aucune tentative.');
    }
}
