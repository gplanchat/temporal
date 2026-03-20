<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Workflow;

/**
 * Échec d’activité de type « application » (équivalent conceptuel d’une ApplicationFailure Temporal).
 *
 * Le flag {@see $nonRetryable} correspond à `non_retryable` côté Temporal : pas de nouvelle tentative.
 */
final class ApplicationActivityFailureException extends \RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly bool $nonRetryable = false,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
