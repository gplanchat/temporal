<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Workflow;

/**
 * Erreur considérée comme **transitoire** côté PoC pour {@see ActivityRetryPolicy} /
 * {@see ActivityRetryRunner} (équivalent conceptuel d’un échec d’activité retryable Temporal).
 */
final class TransientActivityException extends \RuntimeException
{
}
