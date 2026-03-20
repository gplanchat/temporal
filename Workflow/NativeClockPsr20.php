<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Workflow;

use Psr\Clock\ClockInterface;

/**
 * Implémentation PSR-20 du Clock utilisant la date/heure système.
 * Utilisé si symfony/clock n'est pas disponible.
 */
final class NativeClockPsr20 implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
