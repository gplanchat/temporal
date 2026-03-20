<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Client;

use Kiboko\Temporal\Async;
use Kiboko\Temporal\Transport\TransportInterface;

final class WorkflowRun implements WorkflowRunInterface
{
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly string $workflowId,
        private readonly string $runId,
    ) {
    }

    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }

    public function getRunId(): string
    {
        return $this->runId;
    }

    public function getResult(): Async\Awaitable
    {
        return Async\async(fn () => $this->transport->getWorkflowResult($this->workflowId, $this->runId));
    }

    /**
     * Version synchrone (bloquante) pour les cas simples.
     */
    public function getResultSync(): mixed
    {
        return $this->transport->getWorkflowResult($this->workflowId, $this->runId);
    }
}
