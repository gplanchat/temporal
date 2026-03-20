<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Testing;

/**
 * Stub d'activité pour les tests de workflow.
 * Permet de configurer les valeurs de retour sans exécuter l'activité réelle.
 *
 * @template T of object
 */
final class ActivityStub
{
    /** @var array<string, array<int, mixed>> method => [returnValue1, returnValue2, ...] */
    private array $returns = [];

    /** @var array<string, array<int, \Throwable>> method => [exception1, ...] */
    private array $exceptions = [];

    /**
     * @param class-string<T> $interface
     * @return ActivityStub<T>
     */
    public static function for(string $interface): self
    {
        return new self();
    }

    /**
     * Configure la valeur de retour pour un appel de méthode.
     *
     * @return MethodStub<T>
     */
    public function method(string $method): MethodStub
    {
        return new MethodStub($this, $method);
    }

    /**
     * @internal
     */
    public function addReturn(string $method, mixed $value): void
    {
        $this->returns[$method][] = $value;
    }

    /**
     * @internal
     */
    public function addException(string $method, \Throwable $e): void
    {
        $this->exceptions[$method][] = $e;
    }

    /**
     * Consomme et retourne la prochaine valeur configurée pour la méthode.
     *
     * @throws \Throwable Si une exception est configurée
     */
    public function consume(string $method): mixed
    {
        if (isset($this->exceptions[$method]) && $this->exceptions[$method] !== []) {
            $e = array_shift($this->exceptions[$method]);
            throw $e;
        }
        if (isset($this->returns[$method]) && $this->returns[$method] !== []) {
            return array_shift($this->returns[$method]);
        }
        throw new \RuntimeException(sprintf('Aucune valeur configurée pour %s()', $method));
    }
}
