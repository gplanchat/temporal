<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Transport;

use Temporal\Api\Workflowservice\V1\PollWorkflowTaskQueueResponse;
use Temporal\Api\Workflowservice\V1\RespondWorkflowTaskCompletedRequest;

/**
 * Transport capable de poller les tâches workflow Temporal (sans SDK).
 */
interface WorkflowPollTransportInterface
{
    /**
     * Long-poll {@see PollWorkflowTaskQueue}. Retourne null si aucune tâche (timeout / file vide).
     */
    public function pollWorkflowTaskQueue(string $taskQueue, ?float $timeoutSeconds = null): ?PollWorkflowTaskQueueResponse;

    public function respondWorkflowTaskCompleted(RespondWorkflowTaskCompletedRequest $request): void;
}
