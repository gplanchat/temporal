<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Workflow;

use Psr\Clock\ClockInterface;

/**
 * Horloge PSR-20 pouvant être avancée de façon **déterministe** (tests / time-skipping).
 *
 * {@see \Kiboko\Temporal\Testing\TimeSkippingClock} est l’implémentation du PoC.
 */
interface SimulatedClockInterface extends ClockInterface
{
    /**
     * @param \DateInterval|int|float $duration Secondes entières, fraction (voir impl.) ou intervalle.
     */
    public function advance(\DateInterval|int|float $duration): void;
}
