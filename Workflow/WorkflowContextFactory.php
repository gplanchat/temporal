<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Workflow;

use Psr\Clock\ClockInterface;

/**
 * Factory pour créer un WorkflowContext avec des implémentations par défaut.
 */
final class WorkflowContextFactory
{
    /**
     * Crée un contexte avec le Clock système (PSR-20) et symfony/uid.
     */
    public static function create(
        ?ClockInterface $clock = null,
        ?UidGeneratorInterface $uidGenerator = null,
    ): WorkflowContextInterface {
        return new WorkflowContext(
            clock: $clock ?? new NativeClockPsr20(),
            uidGenerator: $uidGenerator ?? new SymfonyUidGenerator(),
        );
    }
}
