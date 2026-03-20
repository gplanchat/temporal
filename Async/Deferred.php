<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Async;

use Revolt\EventLoop\Suspension;

/**
 * Deferred : valeur résolvable une seule fois, compatible avec await().
 */
final class Deferred implements Awaitable
{
    private bool $resolved = false;
    private mixed $value = null;
    private ?\Throwable $error = null;
    /** @var callable[] */
    private array $callbacks = [];

    public function resolve(mixed $value): void
    {
        if ($this->resolved) {
            throw new \LogicException('Deferred already resolved');
        }
        $this->resolved = true;
        $this->value = $value;
        $this->notify();
    }

    public function reject(\Throwable $error): void
    {
        if ($this->resolved) {
            throw new \LogicException('Deferred already resolved');
        }
        $this->resolved = true;
        $this->error = $error;
        $this->notify();
    }

    public function then(callable $onFulfilled, ?callable $onRejected = null): void
    {
        if ($this->resolved) {
            $this->invoke($onFulfilled, $onRejected);
            return;
        }
        $this->callbacks[] = [$onFulfilled, $onRejected];
    }

    private function notify(): void
    {
        foreach ($this->callbacks as $cb) {
            $this->invoke($cb[0], $cb[1]);
        }
        $this->callbacks = [];
    }

    private function invoke(callable $onFulfilled, ?callable $onRejected): void
    {
        if ($this->error !== null) {
            if ($onRejected !== null) {
                $onRejected($this->error);
            } else {
                throw $this->error;
            }
        } else {
            $onFulfilled($this->value);
        }
    }
}
