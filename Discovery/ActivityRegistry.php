<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Discovery;

use Kiboko\Temporal\Attribute\ActivityInterface as ActivityInterfaceAttribute;
use Kiboko\Temporal\Attribute\ActivityMethod;

/**
 * Registre des activités découvertes via les attributs.
 *
 * Mappe activityType (nom Temporal) → [class, method] pour le dispatch.
 */
final class ActivityRegistry
{
    /** @var array<string, array{class: class-string, method: string}> */
    private array $activities = [];

    /**
     * @param iterable<class-string> $classes Classes à scanner (interfaces ou implémentations)
     */
    public function __construct(iterable $classes = [])
    {
        foreach ($classes as $class) {
            $this->register($class);
        }
    }

    /**
     * Enregistre une classe d'activité (implémentation).
     * Si la classe implémente une interface avec #[ActivityInterface], utilise ses méthodes.
     */
    public function register(string $class): void
    {
        if (!class_exists($class)) {
            return;
        }

        $reflection = new \ReflectionClass($class);
        $source = $this->findActivitySource($reflection);
        if ($source === null) {
            return;
        }

        foreach ($source->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->isStatic()) {
                continue;
            }
            $methodAttr = $method->getAttributes(ActivityMethod::class)[0] ?? null;
            $name = $methodAttr?->newInstance()?->name ?? $method->getName();
            $this->activities[$name] = ['class' => $class, 'method' => $method->getName()];
        }
    }

    private function findActivitySource(\ReflectionClass $reflection): ?\ReflectionClass
    {
        if ($reflection->getAttributes(ActivityInterfaceAttribute::class) !== []) {
            return $reflection;
        }
        foreach ($reflection->getInterfaces() as $iface) {
            if ($iface->getAttributes(ActivityInterfaceAttribute::class) !== []) {
                return $iface;
            }
        }
        return null;
    }

    /**
     * Retourne [class, method] pour un type d'activité.
     *
     * @return array{class: class-string, method: string}
     *
     * @throws \InvalidArgumentException Si l'activité n'est pas enregistrée
     */
    public function get(string $activityType): array
    {
        if (!isset($this->activities[$activityType])) {
            throw new \InvalidArgumentException(sprintf('Activité non enregistrée: %s', $activityType));
        }
        return $this->activities[$activityType];
    }

    public function has(string $activityType): bool
    {
        return isset($this->activities[$activityType]);
    }

    /**
     * @return array<string, array{class: class-string, method: string}>
     */
    public function all(): array
    {
        return $this->activities;
    }
}
