<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Workflow;

use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

/**
 * Interface pour la génération d'identifiants uniques dans les workflows.
 * Permet d'injecter symfony/uid ou une implémentation alternative.
 */
interface UidGeneratorInterface
{
    /**
     * Génère un UUID v4 (aléatoire).
     */
    public function uuid4(): Uuid;

    /**
     * Génère un UUID v7 (basé sur le timestamp).
     *
     * @param \DateTimeInterface|null $dateTime Date/heure optionnelle pour la génération
     */
    public function uuid7(?\DateTimeInterface $dateTime = null): Uuid;

    /**
     * Génère un ULID (lexicographiquement triable).
     */
    public function ulid(): Ulid;
}
