<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Workflow;

use Kiboko\Temporal\Async;

/**
 * Exécute un workflow avec un contexte injecté (Clock, UidGenerator).
 */
final class WorkflowRunner
{
    public function __construct(
        private readonly WorkflowContextInterface $context,
    ) {
    }

    /**
     * Exécute un workflow avec le contexte injecté.
     * Le contexte est disponible via WorkflowContextHolder pendant l'exécution.
     *
     * @template T
     * @param callable(): T $workflow
     * @return T
     */
    public function run(callable $workflow): mixed
    {
        WorkflowContextHolder::set($this->context);
        try {
            return $workflow();
        } finally {
            WorkflowContextHolder::clear();
        }
    }

    /**
     * Exécute un workflow de manière asynchrone (dans l'event loop).
     *
     * @template T
     * @param callable(): T $workflow
     * @return Async\Awaitable<T>
     */
    public function runAsync(callable $workflow): Async\Awaitable
    {
        return Async\async(function () use ($workflow): mixed {
            return $this->run($workflow);
        });
    }
}
