<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Transport;

/**
 * Transport pour communiquer avec Temporal (HTTP, gRPC, in-memory, etc.).
 */
interface TransportInterface
{
    public function ping(): bool;

    /**
     * @param array<string, mixed> $options
     *
     * @return array{workflowId: string, runId: string}
     */
    public function startWorkflow(string $workflowType, array $input = [], array $options = []): array;

    public function getWorkflowResult(string $workflowId, string $runId): mixed;

    /**
     * @return array{taskToken: string, activityType: string, input: array, workflowId: string, runId: string}|null
     */
    public function pollActivityTaskQueue(string $taskQueue, ?float $timeoutSeconds = null): ?array;

    public function respondActivityTaskCompleted(string $taskToken, mixed $result): void;

    public function respondActivityTaskFailed(string $taskToken, string $failure): void;
}
