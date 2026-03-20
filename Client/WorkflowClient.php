<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Client;

use Kiboko\Temporal\Transport\TransportInterface;

/**
 * Client haut niveau pour démarrer des workflows et récupérer les résultats.
 */
final class WorkflowClient implements ClientInterface
{
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly string $namespace = 'default',
        private readonly string $taskQueue = 'default',
    ) {
    }

    /**
     * @param class-string $workflowClass
     * @param array<string, mixed> $args
     */
    public function startWorkflow(string $workflowClass, array $args = []): WorkflowRunInterface
    {
        $options = [
            'taskQueue' => $this->taskQueue,
            'namespace' => $this->namespace,
        ];
        $result = $this->transport->startWorkflow($workflowClass, $args, $options);
        return new WorkflowRun($this->transport, $result['workflowId'], $result['runId']);
    }

    public function getWorkflowRun(string $workflowId, string $runId): WorkflowRunInterface
    {
        return new WorkflowRun($this->transport, $workflowId, $runId);
    }
}
