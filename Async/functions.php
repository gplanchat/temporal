<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Async;

use Revolt\EventLoop;

/**
 * Primitives async basées sur Fibers et revolt/event-loop.
 * Évite le problème des fonctions colorées (blue/red).
 */

/**
 * Attend la résolution d'un Awaitable et retourne la valeur.
 * Suspend la Fiber courante jusqu'à résolution.
 *
 * @template T
 * @param Awaitable<T>|callable(): Awaitable<T> $awaitable
 * @return T
 */
function await(Awaitable|callable $awaitable): mixed
{
    $a = $awaitable instanceof Awaitable ? $awaitable : $awaitable();
    $suspension = EventLoop::getSuspension();

    $a->then(
        fn (mixed $value) => $suspension->resume($value),
        fn (\Throwable $e) => $suspension->throw($e)
    );

    return $suspension->suspend();
}

/**
 * Exécute un callable dans une Fiber, retourne un Awaitable.
 * La tâche enfant est annulée si la tâche parent est annulée.
 *
 * @template T
 * @param callable(): T $callable
 * @return Awaitable<T>
 */
function async(callable $callable): Awaitable
{
    $deferred = new Deferred();

    $fiber = new \Fiber(function () use ($callable, $deferred): void {
        try {
            $result = $callable();
            $deferred->resolve($result);
        } catch (\Throwable $e) {
            $deferred->reject($e);
        }
    });

    EventLoop::defer(function () use ($fiber): void {
        $fiber->start();
    });

    return $deferred;
}

/**
 * Exécute un callable dans une Fiber détachée, non affectée par l'annulation du parent.
 *
 * Contrairement à async(), la tâche créée n'est pas annulée si la tâche parent est
 * annulée ou interrompue. Utile pour la logique de cleanup et de compensation (Saga).
 *
 * @see https://php.temporal.io/classes/Temporal-Workflow.html#method_asyncDetached
 *
 * @template T
 * @param callable(): T $callable
 * @return Awaitable<T>
 */
function asyncDetached(callable $callable): Awaitable
{
    // Même implémentation que async() pour l'instant. Lors de l'ajout du support
    // de l'annulation (CancellationScope), la tâche détachée ne sera pas annulée
    // avec le scope parent.
    return async($callable);
}

/**
 * Exécute un Generator comme coroutine (équivalent ReactPHP avec Fibers).
 *
 * @template T
 * @param \Generator<mixed, mixed, mixed, T> $generator
 * @return Awaitable<T>
 */
function coroutine(\Generator $generator): Awaitable
{
    return async(function () use ($generator) {
        $value = $generator->current();
        while ($generator->valid()) {
            $toSend = $value instanceof Awaitable ? await($value) : null;
            $value = $generator->send($toSend);
        }
        return $generator->getReturn();
    });
}

/**
 * Pause non bloquante de $seconds secondes.
 */
function delay(float $seconds): void
{
    $suspension = EventLoop::getSuspension();
    $id = EventLoop::delay($seconds, fn () => $suspension->resume(null));
    $suspension->suspend();
    EventLoop::cancel($id);
}

/**
 * Attend que {@see $condition} retourne true, ou jusqu’à expiration de {@see $timeoutSeconds}.
 *
 * Boucle non bloquante via {@see delay()} entre chaque test de la condition (utile pour signaux / état
 * partagé **hors** moteur de replay Temporal strict — voir docs/ROADMAP_TRANSPORT_SDK_WORKFLOWS.md).
 *
 * @param callable(): bool $condition
 */
function awaitWithTimeout(float $timeoutSeconds, callable $condition, float $pollIntervalSeconds = 0.05): bool
{
    if ($timeoutSeconds <= 0) {
        return $condition();
    }

    $deadline = microtime(true) + $timeoutSeconds;
    while (microtime(true) < $deadline) {
        if ($condition()) {
            return true;
        }
        delay(max(0.0, min($pollIntervalSeconds, $deadline - microtime(true))));
    }

    return false;
}

/**
 * Exécute des callables en parallèle et attend que tous soient résolus.
 *
 * @template T
 * @param array<callable(): T> $callables
 * @return array<T>
 */
function parallel(array $callables): array
{
    return await(all(array_map(fn (callable $c) => async($c), $callables)));
}

/**
 * Attend que tous les Awaitables soient résolus.
 *
 * @template T
 * @param array<Awaitable<T>> $awaitables
 * @return Awaitable<array<T>>
 */
function all(array $awaitables): Awaitable
{
    $deferred = new Deferred();
    $results = [];
    $errors = [];
    $remaining = \count($awaitables);

    if ($remaining === 0) {
        $deferred->resolve([]);
        return $deferred;
    }

    foreach ($awaitables as $i => $a) {
        $a->then(
            function (mixed $value) use ($i, &$results, &$remaining, $deferred): void {
                $results[$i] = $value;
                if (--$remaining === 0) {
                    \ksort($results);
                    $deferred->resolve(array_values($results));
                }
            },
            function (\Throwable $e) use ($deferred, &$errors): void {
                $errors[] = $e;
                $deferred->reject($e);
            }
        );
    }

    return $deferred;
}

/**
 * Attend que le premier Awaitable soit résolu.
 *
 * @template T
 * @param array<Awaitable<T>> $awaitables
 * @return Awaitable<T>
 */
function any(array $awaitables): Awaitable
{
    return race($awaitables);
}

/**
 * Attend que le premier Awaitable soit résolu (premier gagnant).
 *
 * @template T
 * @param array<Awaitable<T>> $awaitables
 * @return Awaitable<T>
 */
function race(array $awaitables): Awaitable
{
    $deferred = new Deferred();
    $resolved = false;

    foreach ($awaitables as $a) {
        $a->then(
            function (mixed $value) use ($deferred, &$resolved): void {
                if (!$resolved) {
                    $resolved = true;
                    $deferred->resolve($value);
                }
            },
            function (\Throwable $e) use ($deferred, &$resolved): void {
                if (!$resolved) {
                    $resolved = true;
                    $deferred->reject($e);
                }
            }
        );
    }

    return $deferred;
}
