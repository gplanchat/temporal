<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Testing;

use Kiboko\Temporal\Discovery\ActivityRegistry;
use Kiboko\Temporal\Messenger\ActivityTask;
use Kiboko\Temporal\Messenger\ActivityTaskHandler;
use Psr\Container\ContainerInterface;

/**
 * Exécute une activité en isolation avec un payload donné.
 * Utile pour tester une activité sans lancer le workflow complet.
 */
final class ActivityTestRunner
{
    public function __construct(
        private readonly ActivityRegistry $registry,
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * Exécute une activité par son type Temporal.
     *
     * @param array<string, mixed> $input Payload d'entrée (clés = noms des paramètres)
     * @return mixed Résultat de l'activité
     */
    public function run(string $activityType, array $input): mixed
    {
        $task = new ActivityTask(
            activityType: $activityType,
            input: $input,
            taskToken: 'test-token',
            workflowId: 'test-workflow',
            runId: 'test-run',
        );

        return $this->runTask($task);
    }

    /**
     * Exécute une tâche d'activité complète.
     */
    public function runTask(ActivityTask $task): mixed
    {
        $handler = new ActivityTaskHandler($this->registry, $this->container);

        return $handler($task);
    }
}
