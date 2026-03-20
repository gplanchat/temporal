<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Workflow;

use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

/**
 * Implémentation du contexte workflow avec injection de dépendances.
 */
final class WorkflowContext implements WorkflowContextInterface
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly UidGeneratorInterface $uidGenerator,
    ) {
    }

    public function getClock(): ClockInterface
    {
        return $this->clock;
    }

    public function now(): \DateTimeImmutable
    {
        return $this->clock->now();
    }

    public function uuid4(): Uuid
    {
        return $this->uidGenerator->uuid4();
    }

    public function uuid7(?\DateTimeInterface $dateTime = null): Uuid
    {
        return $this->uidGenerator->uuid7($dateTime);
    }

    public function ulid(): Ulid
    {
        return $this->uidGenerator->ulid();
    }
}
