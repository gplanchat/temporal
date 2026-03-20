<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Testing;

use Kiboko\Temporal\Workflow\Workflow;

/**
 * Façade de compatibilité : délègue à {@see Workflow::advanceSimulatedTime()}.
 *
 * À utiliser dans des workflows de test ou un `callable(float $seconds): void` injecté dans
 * {@see \Kiboko\Temporal\Workflow\DelayedEchoWorkflow} pour éviter {@see \Kiboko\Temporal\Async\delay} wall-clock.
 */
final class WorkflowSimulatedTime
{
    /**
     * @param \DateInterval|int|float $duration Même sémantique que {@see TimeSkippingClock::advance()}.
     */
    public static function advance(\DateInterval|int|float $duration): void
    {
        Workflow::advanceSimulatedTime($duration);
    }
}
