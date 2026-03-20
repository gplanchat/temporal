<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Workflow;

use Kiboko\Temporal\Async;

/**
 * Saga pattern natif pour les workflows.
 *
 * Permet d'enregistrer des actions de compensation après chaque étape réussie.
 * En cas d'échec, les compensations sont exécutées en ordre inverse (LIFO).
 *
 * Compatible avec l'API Temporal Workflow Saga.
 *
 * @see https://php.temporal.io/classes/Temporal-Workflow-Saga.html
 */
final class Saga
{
    /** @var list<callable(): (void|Async\Awaitable)> */
    private array $compensations = [];

    private bool $parallelCompensation = false;

    private bool $continueWithError = false;

    /**
     * Enregistre une action de compensation.
     * Les compensations sont exécutées en ordre inverse (dernier ajouté = premier exécuté).
     *
     * @param callable(): (void|Async\Awaitable) $handler Action de compensation (peut être async)
     */
    public function addCompensation(callable $handler): void
    {
        $this->compensations[] = $handler;
    }

    /**
     * Exécute les compensations en ordre inverse (LIFO).
     * À appeler dans un bloc catch après l'échec d'une étape.
     *
     * @return Async\Awaitable<void>
     */
    public function compensate(): Async\Awaitable
    {
        if ($this->compensations === []) {
            return Async\async(static fn () => null);
        }

        if ($this->parallelCompensation) {
            return $this->compensateParallel();
        }

        return $this->compensateSequential();
    }

    /**
     * Compensations en parallèle (toutes exécutées simultanément).
     */
    public function setParallelCompensation(bool $parallel = true): self
    {
        $this->parallelCompensation = $parallel;
        return $this;
    }

    /**
     * En mode séquentiel : continuer avec les compensations suivantes si une lève une exception.
     */
    public function setContinueWithError(bool $continue = true): self
    {
        $this->continueWithError = $continue;
        return $this;
    }

    private function compensateSequential(): Async\Awaitable
    {
        return Async\async(function (): void {
            $handlers = array_reverse($this->compensations);
            foreach ($handlers as $handler) {
                try {
                    $result = $handler();
                    if ($result instanceof Async\Awaitable) {
                        Async\await($result);
                    }
                } catch (\Throwable $e) {
                    if (!$this->continueWithError) {
                        throw $e;
                    }
                }
            }
        });
    }

    private function compensateParallel(): Async\Awaitable
    {
        $awaitables = array_map(function (callable $handler): Async\Awaitable {
            return Async\async(function () use ($handler): void {
                $result = $handler();
                if ($result instanceof Async\Awaitable) {
                    Async\await($result);
                }
            });
        }, array_reverse($this->compensations));

        return Async\async(fn () => Async\await(Async\all($awaitables)));
    }
}
