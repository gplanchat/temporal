<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Http;

use Kiboko\Temporal\Retry\ExponentialBackoffPolicy;

/**
 * Politique de retry pour les appels **PSR-18** vers l’API HTTP Temporal (alignée sur {@see \Kiboko\Temporal\Grpc\GrpcUnaryRetryPolicy} côté backoff).
 */
final class HttpTransientRetryPolicy
{
    private readonly ExponentialBackoffPolicy $backoff;

    /**
     * @param list<int> $retryableStatusCodes Codes HTTP « transitoires » usuels.
     */
    public function __construct(
        public readonly int $maxAttempts = 3,
        int $initialBackoffMicroseconds = 100_000,
        float $backoffMultiplier = 2.0,
        int $maxBackoffMicroseconds = 2_000_000,
        public readonly array $retryableStatusCodes = [
            408, // Request Timeout
            425, // Too Early
            429, // Too Many Requests
            500,
            502,
            503,
            504,
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

    /**
     * @see GrpcTransport::unaryRetryPolicyFromEnvironment() pour la sémantique des entiers.
     */
    public static function fromEnvironment(): ?self
    {
        $raw = getenv('POC_TEMPORAL_HTTP_MAX_RETRIES');
        if (false === $raw || '' === $raw) {
            return new self();
        }

        $n = (int) $raw;
        if ($n <= 1) {
            return null;
        }

        return new self(maxAttempts: $n);
    }

    public function isRetryableStatusCode(int $httpStatusCode): bool
    {
        return \in_array($httpStatusCode, $this->retryableStatusCodes, true);
    }

    public function backoffMicrosecondsForAttempt(int $attemptIndexZeroBased): int
    {
        return $this->backoff->microsecondsForAttempt($attemptIndexZeroBased);
    }
}
