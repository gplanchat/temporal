<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Http;

/**
 * @template T
 */
final class HttpPsr18RetryRunner
{
    /**
     * @param callable(): T $once
     *
     * @return T
     */
    public static function runWithRetry(callable $once, HttpTransientRetryPolicy $policy): mixed
    {
        $lastError = null;

        for ($attempt = 0; $attempt < $policy->maxAttempts; ++$attempt) {
            try {
                return $once();
            } catch (HttpRetryableResponseException $e) {
                $lastError = $e;
                if ($attempt + 1 >= $policy->maxAttempts || !$policy->isRetryableStatusCode($e->statusCode)) {
                    throw $e;
                }
                usleep($policy->backoffMicrosecondsForAttempt($attempt));
            }
        }

        throw $lastError ?? new \LogicException('HttpPsr18RetryRunner : boucle vide.');
    }
}
