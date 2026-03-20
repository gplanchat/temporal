<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Client;

use Kiboko\Temporal\Async\Awaitable;

/**
 * Handle sur une exécution de workflow en cours ou terminée.
 */
interface WorkflowRunInterface
{
    public function getWorkflowId(): string;

    public function getRunId(): string;

    /**
     * Attend la fin du workflow et retourne le résultat.
     *
     * @return Awaitable<mixed>
     */
    public function getResult(): Awaitable;
}
