<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Async;

/**
 * Interface pour les valeurs awaitables (résolvables via Suspension).
 */
interface Awaitable
{
    /**
     * Attache un callback à appeler lorsque la valeur est résolue.
     * Le callback reçoit (mixed $value) ou (Throwable $error).
     */
    public function then(callable $onFulfilled, ?callable $onRejected = null): void;
}
