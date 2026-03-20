<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Testing;

/**
 * Fluent builder pour configurer les retours d'une méthode de stub.
 *
 * @template T of object
 */
final class MethodStub
{
    public function __construct(
        private readonly ActivityStub $stub,
        private readonly string $method,
    ) {
    }

    /**
     * @return ActivityStub<T>
     */
    public function willReturn(mixed ...$values): ActivityStub
    {
        foreach ($values as $value) {
            $this->stub->addReturn($this->method, $value);
        }
        return $this->stub;
    }

    /**
     * @return ActivityStub<T>
     */
    public function willThrow(\Throwable $exception): ActivityStub
    {
        $this->stub->addException($this->method, $exception);
        return $this->stub;
    }
}
