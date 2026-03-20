<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Client;

/**
 * Interface du client Temporal minimal.
 * Permet de démarrer des workflows et de communiquer avec le serveur Temporal.
 */
interface ClientInterface
{
    /**
     * Démarre une exécution de workflow.
     *
     * @param class-string $workflowClass
     * @param array<string, mixed> $args
     */
    public function startWorkflow(string $workflowClass, array $args = []): WorkflowRunInterface;

    /**
     * Récupère un handle sur une exécution de workflow existante.
     */
    public function getWorkflowRun(string $workflowId, string $runId): WorkflowRunInterface;
}
