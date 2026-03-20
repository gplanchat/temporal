<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Workflow;

use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

/**
 * Contexte d'exécution d'un workflow.
 * Fournit l'accès au temps (Clock) et aux identifiants (Uid) de manière injectable.
 */
interface WorkflowContextInterface
{
    /**
     * Horloge PSR-20 sous-jacente (tests : {@see \Kiboko\Temporal\Testing\TimeSkippingClock}).
     */
    public function getClock(): ClockInterface;

    /**
     * Retourne la date/heure courante (déterministe en replay).
     * Délègue au Clock PSR-20 injecté.
     */
    public function now(): \DateTimeImmutable;

    /**
     * Génère un UUID v4.
     */
    public function uuid4(): Uuid;

    /**
     * Génère un UUID v7.
     *
     * @param \DateTimeInterface|null $dateTime Date/heure optionnelle
     */
    public function uuid7(?\DateTimeInterface $dateTime = null): Uuid;

    /**
     * Génère un ULID.
     */
    public function ulid(): Ulid;
}
