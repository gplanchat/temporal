<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Workflow;

use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

/**
 * Facade d'accès au contexte workflow courant.
 *
 * Méthodes statiques qui délèguent au WorkflowContext injecté.
 * Le contexte doit être défini via WorkflowRunner::run() ou WorkflowContextHolder::set().
 *
 * @see https://php.temporal.io/classes/Temporal-Workflow.html
 */
final class Workflow
{
    /**
     * Retourne la date/heure courante (Clock PSR-20).
     */
    public static function now(): \DateTimeImmutable
    {
        return WorkflowContextHolder::get()->now();
    }

    /**
     * Génère un UUID v4.
     */
    public static function uuid4(): Uuid
    {
        return WorkflowContextHolder::get()->uuid4();
    }

    /**
     * Génère un UUID v7.
     *
     * @param \DateTimeInterface|null $dateTime Date/heure optionnelle
     */
    public static function uuid7(?\DateTimeInterface $dateTime = null): Uuid
    {
        return WorkflowContextHolder::get()->uuid7($dateTime);
    }

    /**
     * Génère un ULID.
     */
    public static function ulid(): Ulid
    {
        return WorkflowContextHolder::get()->ulid();
    }

    /**
     * Avance l’horloge **simulée** si le contexte expose une {@see SimulatedClockInterface} (ex. {@see \Kiboko\Temporal\Testing\TimeSkippingClock}).
     *
     * Alternative pratique à l’import direct de {@see \Kiboko\Temporal\Testing\WorkflowSimulatedTime} dans les workflows de démo / tests.
     *
     * @param \DateInterval|int|float $duration Même sémantique que {@see \Kiboko\Temporal\Testing\TimeSkippingClock::advance()}.
     */
    public static function advanceSimulatedTime(\DateInterval|int|float $duration): void
    {
        $clock = WorkflowContextHolder::get()->getClock();
        if (!$clock instanceof SimulatedClockInterface) {
            throw new \BadMethodCallException(
                'L’horloge du contexte n’implémente pas SimulatedClockInterface ; impossible d’avancer le temps simulé.',
            );
        }

        $clock->advance($duration);
    }
}
