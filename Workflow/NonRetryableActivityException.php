<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Workflow;

/**
 * Échec d’activité explicitement **non retryable** (équivalent erreur métier définitive).
 */
final class NonRetryableActivityException extends \RuntimeException
{
}
