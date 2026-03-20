<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Grpc;

use Google\Protobuf\Duration;
use Symfony\Component\Uid\Uuid;
use Temporal\Api\Command\V1\Command;
use Temporal\Api\Command\V1\StartChildWorkflowExecutionCommandAttributes;
use Temporal\Api\Common\V1\WorkflowType;
use Temporal\Api\Enums\V1\CommandType;
use Temporal\Api\Taskqueue\V1\TaskQueue;

/**
 * Construit une commande gRPC Temporal {@see CommandType::COMMAND_TYPE_START_CHILD_WORKFLOW_EXECUTION}.
 *
 * Temporal n’expose **pas** de RPC unary `StartChildWorkflowExecution` côté client : le démarrage d’un enfant
 * se fait via {@see \Temporal\Api\Workflowservice\V1\RespondWorkflowTaskCompletedRequest} (liste de {@see Command}).
 */
final class StartChildWorkflowCommandFactory
{
    /**
     * @param array<string, mixed> $namedInput ex. `['input' => 'hello']` — même convention que {@see TemporalPayloadMapper::payloadsFromAssociativeInput()}.
     */
    public static function createCommand(
        TemporalPayloadMapper $mapper,
        string $namespace,
        string $childWorkflowTypeName,
        string $taskQueue,
        array $namedInput,
        ?string $childWorkflowId = null,
    ): Command {
        $id = $childWorkflowId ?? ('poc-child-' . Uuid::v4()->toRfc4122());

        return new Command([
            'command_type' => CommandType::COMMAND_TYPE_START_CHILD_WORKFLOW_EXECUTION,
            'start_child_workflow_execution_command_attributes' => new StartChildWorkflowExecutionCommandAttributes([
                'namespace' => $namespace,
                'workflow_id' => $id,
                'workflow_type' => new WorkflowType(['name' => $childWorkflowTypeName]),
                'task_queue' => new TaskQueue(['name' => $taskQueue]),
                'input' => $mapper->payloadsFromAssociativeInput($namedInput),
                'workflow_execution_timeout' => new Duration(['seconds' => 300]),
                'workflow_run_timeout' => new Duration(['seconds' => 120]),
                'workflow_task_timeout' => new Duration(['seconds' => 30]),
            ]),
        ]);
    }
}
