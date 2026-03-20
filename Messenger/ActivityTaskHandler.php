<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Messenger;

use Kiboko\Temporal\Discovery\ActivityRegistry;
use Psr\Container\ContainerInterface;

/**
 * Dispatch des tâches d’activité vers les implémentations enregistrées (attributs Activity).
 * La couche Symfony Messenger ajoute {@see \Kiboko\TemporalBundle\Messenger\GenericActivityTaskHandler}.
 */
final class ActivityTaskHandler
{
    public function __construct(
        private readonly ActivityRegistry $registry,
        private readonly ContainerInterface $container,
    ) {
    }

    public function __invoke(ActivityTask $task): mixed
    {
        $activity = $this->registry->get($task->activityType);
        $instance = $this->container->get($activity['class']);
        $method = $activity['method'];

        $args = $this->resolveArguments($task->input, $instance, $method);

        return $instance->{$method}(...$args);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<int, mixed>
     */
    private function resolveArguments(array $input, object $instance, string $method): array
    {
        $reflection = new \ReflectionMethod($instance, $method);
        $params = $reflection->getParameters();
        $args = [];

        foreach ($params as $param) {
            $name = $param->getName();
            if (isset($input[$name])) {
                $args[] = $input[$name];
            } elseif (\array_key_exists('input', $input) && \count($params) === 1) {
                $args[] = $input['input'];
            } else {
                $args[] = $param->getDefaultValue();
            }
        }

        return $args;
    }
}
