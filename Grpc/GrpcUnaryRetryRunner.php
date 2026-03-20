<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Grpc;

/**
 * Exécute un callable avec retry sur {@see GrpcUnaryCallFailedException} selon {@see GrpcUnaryRetryPolicy}.
 *
 * Factorisé pour être couvert par des tests unitaires **sans** réseau ni extension grpc.
 */
final class GrpcUnaryRetryRunner
{
    /**
     * @template T
     *
     * @param callable(): T $once
     *
     * @return T
     */
    public static function runWithRetry(callable $once, GrpcUnaryRetryPolicy $policy): mixed
    {
        $lastError = null;

        for ($attempt = 0; $attempt < $policy->maxAttempts; ++$attempt) {
            try {
                return $once();
            } catch (GrpcUnaryCallFailedException $e) {
                $lastError = $e;
                if ($attempt + 1 >= $policy->maxAttempts || !$policy->isRetryableStatusCode($e->grpcStatusCode)) {
                    throw $e;
                }
                usleep($policy->backoffMicrosecondsForAttempt($attempt));
            }
        }

        throw $lastError ?? new \LogicException('runWithRetry : boucle vide.');
    }
}
