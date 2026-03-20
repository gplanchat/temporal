<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Http;

/**
 * Réponse HTTP considérée comme **transitoire** : déclenche un retry côté {@see RetryingPsr18Client}.
 */
final class HttpRetryableResponseException extends \RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        string $message = 'HTTP transient status',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
