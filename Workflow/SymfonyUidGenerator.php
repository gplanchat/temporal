<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Workflow;

use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

/**
 * Implémentation du générateur UID basée sur symfony/uid.
 */
final class SymfonyUidGenerator implements UidGeneratorInterface
{
    public function uuid4(): Uuid
    {
        return Uuid::v4();
    }

    public function uuid7(?\DateTimeInterface $dateTime = null): Uuid
    {
        return new UuidV7(UuidV7::generate($dateTime));
    }

    public function ulid(): Ulid
    {
        return new Ulid();
    }
}
