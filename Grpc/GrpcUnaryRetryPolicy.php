<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Grpc;

use Kiboko\Temporal\Retry\ExponentialBackoffPolicy;

/**
 * Politique de retry pour les appels gRPC **unary** vers Temporal (ping, start, respond, etc.).
 *
 * Ne s’applique pas aux longs polls {@see \Grpc\BidiStreamingCall} / streams — le retry y est traité
 * au niveau boucle transport / Messenger.
 *
 * Codes gRPC : https://grpc.github.io/grpc/core/md_doc_statuscodes.html
 */
final class GrpcUnaryRetryPolicy
{
    private readonly ExponentialBackoffPolicy $backoff;

    /** @param list<int> $retryableStatusCodes Codes numériques {@see \Grpc\STATUS_*} */
    public function __construct(
        public readonly int $maxAttempts = 3,
        public readonly int $initialBackoffMicroseconds = 100_000,
        public readonly float $backoffMultiplier = 2.0,
        public readonly int $maxBackoffMicroseconds = 2_000_000,
        public readonly array $retryableStatusCodes = [
            4, // DEADLINE_EXCEEDED
            8, // RESOURCE_EXHAUSTED
            10, // ABORTED
            14, // UNAVAILABLE
        ],
    ) {
        if ($maxAttempts < 1) {
            throw new \InvalidArgumentException('maxAttempts doit être >= 1.');
        }
        $this->backoff = new ExponentialBackoffPolicy($initialBackoffMicroseconds, $backoffMultiplier, $maxBackoffMicroseconds);
    }

    public static function disabled(): self
    {
        return new self(maxAttempts: 1);
    }

    public function isRetryableStatusCode(int $code): bool
    {
        $ok = \defined('Grpc\STATUS_OK') ? (int) \Grpc\STATUS_OK : 0;
        if ($code === $ok) {
            return false;
        }

        return \in_array($code, $this->retryableStatusCodes, true);
    }

    public function backoffMicrosecondsForAttempt(int $attemptIndexZeroBased): int
    {
        return $this->backoff->microsecondsForAttempt($attemptIndexZeroBased);
    }
}
