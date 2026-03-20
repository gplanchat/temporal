<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Testing;

use Kiboko\Temporal\Workflow\WorkflowContext;
use Kiboko\Temporal\Workflow\WorkflowContextInterface;
use Kiboko\Temporal\Workflow\WorkflowRunner;
use Kiboko\Temporal\Workflow\SymfonyUidGenerator;

/**
 * Environnement de test permettant d'avancer le temps simulé.
 *
 * Utile pour tester les workflows qui utilisent Workflow::now(), timers ou sleep.
 * Le temps peut être avancé sans attendre réellement.
 */
final class TimeSkippingWorkflowEnvironment
{
    private TimeSkippingClock $clock;

    public function __construct(?\DateTimeImmutable $initialTime = null)
    {
        $this->clock = new TimeSkippingClock($initialTime);
    }

    public function getClock(): TimeSkippingClock
    {
        return $this->clock;
    }

    /**
     * Crée un WorkflowContext avec l'horloge simulée.
     */
    public function createContext(): WorkflowContextInterface
    {
        return new WorkflowContext(
            clock: $this->clock,
            uidGenerator: new SymfonyUidGenerator(),
        );
    }

    /**
     * Exécute un callable avec le contexte (horloge simulée).
     *
     * @template T
     * @param callable(): T $workflow
     * @return T
     */
    public function run(callable $workflow): mixed
    {
        $context = $this->createContext();
        $runner = new WorkflowRunner($context);

        return $runner->run($workflow);
    }
}
