<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Transport;

use Kiboko\Temporal\Transport\TransportInterface;

/**
 * Transport en mémoire pour les tests.
 * Simule l'API Temporal sans serveur.
 */
final class InMemoryTransport implements TransportInterface
{
    /** @var array<int, array{workflowId: string, runId: string, result: mixed}> */
    private array $workflowResults = [];
    private int $nextWorkflowId = 0;

    /** @var list<array{taskToken: string, activityType: string, input: array, workflowId: string, runId: string}> */
    private array $activityQueue = [];
    private int $nextTaskToken = 0;

    public function ping(): bool
    {
        return true;
    }

    public function startWorkflow(string $workflowType, array $input = [], array $options = []): array
    {
        $workflowId = $options['workflowId'] ?? ('wf-' . (++$this->nextWorkflowId));
        $runId = 'run-' . uniqid();
        $this->workflowResults[] = ['workflowId' => $workflowId, 'runId' => $runId, 'result' => null];
        return ['workflowId' => $workflowId, 'runId' => $runId];
    }

    public function setWorkflowResult(string $workflowId, string $runId, mixed $result): void
    {
        foreach ($this->workflowResults as &$wf) {
            if ($wf['workflowId'] === $workflowId && $wf['runId'] === $runId) {
                $wf['result'] = $result;
                return;
            }
        }
        $this->workflowResults[] = ['workflowId' => $workflowId, 'runId' => $runId, 'result' => $result];
    }

    public function getWorkflowResult(string $workflowId, string $runId): mixed
    {
        foreach ($this->workflowResults as $wf) {
            if ($wf['workflowId'] === $workflowId && $wf['runId'] === $runId) {
                return $wf['result'];
            }
        }
        return null;
    }

    public function enqueueActivity(string $activityType, array $input, string $workflowId = 'test', string $runId = 'test'): void
    {
        $this->activityQueue[] = [
            'taskToken' => (string) (++$this->nextTaskToken),
            'activityType' => $activityType,
            'input' => $input,
            'workflowId' => $workflowId,
            'runId' => $runId,
        ];
    }

    public function pollActivityTaskQueue(string $taskQueue, ?float $timeoutSeconds = null): ?array
    {
        if ($this->activityQueue === []) {
            return null;
        }
        return array_shift($this->activityQueue);
    }

    public function respondActivityTaskCompleted(string $taskToken, mixed $result): void
    {
        // En mémoire : rien à faire
    }

    public function respondActivityTaskFailed(string $taskToken, string $failure): void
    {
        // En mémoire : rien à faire
    }
}
