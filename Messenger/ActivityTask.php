<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Messenger;

/**
 * Message représentant une tâche d'activité Temporal.
 * En production, ce message serait créé à partir des données reçues du serveur Temporal.
 */
final class ActivityTask
{
    public function __construct(
        public readonly string $activityType,
        public readonly array $input,
        public readonly string $taskToken,
        public readonly string $workflowId,
        public readonly string $runId,
    ) {
    }
}
