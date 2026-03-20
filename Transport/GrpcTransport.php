<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Transport;

use Kiboko\Temporal\Transport\TransportInterface;
use Kiboko\Temporal\Grpc\GrpcTemporalRpc;
use Kiboko\Temporal\Grpc\GrpcUnaryRetryPolicy;
use Kiboko\Temporal\Grpc\TemporalGrpcTlsConfig;
use Kiboko\Temporal\Grpc\TemporalPayloadMapper;
use Symfony\Component\Uid\Uuid;
use Temporal\Api\Common\V1\WorkflowExecution;
use Temporal\Api\Common\V1\WorkflowType;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Api\Taskqueue\V1\TaskQueue;
use Temporal\Api\Workflowservice\V1\GetClusterInfoRequest;
use Temporal\Api\Workflowservice\V1\GetClusterInfoResponse;
use Temporal\Api\Workflowservice\V1\GetSystemInfoRequest;
use Temporal\Api\Workflowservice\V1\GetSystemInfoResponse;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryRequest;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryResponse;
use Temporal\Api\Workflowservice\V1\PollActivityTaskQueueRequest;
use Temporal\Api\Workflowservice\V1\PollActivityTaskQueueResponse;
use Temporal\Api\Workflowservice\V1\PollWorkflowTaskQueueRequest;
use Temporal\Api\Workflowservice\V1\PollWorkflowTaskQueueResponse;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedRequest;
use Temporal\Api\Workflowservice\V1\RespondWorkflowTaskCompletedRequest;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionResponse;

/**
 * Transport Temporal via gRPC (extension grpc) — pas de SDK Temporal ni RoadRunner.
 *
 * Démarrage d’un **workflow enfant** : pas de RPC unary dédié côté {@see \Temporal\Api\Workflowservice\V1\WorkflowServiceClient} ;
 * utiliser une commande {@see \Temporal\Api\Enums\V1\CommandType::COMMAND_TYPE_START_CHILD_WORKFLOW_EXECUTION} dans
 * {@see \Temporal\Api\Workflowservice\V1\RespondWorkflowTaskCompletedRequest} — fabrique {@see \Kiboko\Temporal\Grpc\StartChildWorkflowCommandFactory}.
 */
final class GrpcTransport implements TransportInterface, WorkflowPollTransportInterface
{
    public function __construct(
        private readonly GrpcTemporalRpc $rpc,
        private readonly TemporalPayloadMapper $mapper,
        private readonly string $namespace = 'default',
        private readonly string $identity = 'temporal-php',
    ) {
    }

    public static function createDefault(
        string $grpcTarget,
        TemporalPayloadMapper $mapper,
        string $namespace = 'default',
        ?TemporalGrpcTlsConfig $tlsConfig = null,
    ): self {
        $retryPolicy = self::unaryRetryPolicyFromEnvironment();
        $rpc = new GrpcTemporalRpc($grpcTarget, 'temporal-php', $retryPolicy, null, $tlsConfig);

        return new self($rpc, $mapper, $namespace, $rpc->getDefaultIdentity());
    }

    /**
     * {@see GrpcUnaryRetryPolicy} pour les appels unary (ping, start workflow, respond, …).
     *
     * - Variable absente ou vide → politique par défaut (3 tentatives, backoff).
     * - `POC_TEMPORAL_GRPC_UNARY_MAX_RETRIES=1` ou `0` → pas de retry (comportement historique).
     * - Entier ≥ 2 → ce nombre de tentatives au maximum.
     */
    public static function unaryRetryPolicyFromEnvironment(): ?GrpcUnaryRetryPolicy
    {
        $raw = getenv('POC_TEMPORAL_GRPC_UNARY_MAX_RETRIES');
        if (false === $raw || '' === $raw) {
            return new GrpcUnaryRetryPolicy();
        }

        $n = (int) $raw;
        if ($n <= 1) {
            return null;
        }

        return new GrpcUnaryRetryPolicy(maxAttempts: $n);
    }

    public function ping(): bool
    {
        return $this->pingDetailed()['ok'];
    }

    /**
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function pingDetailed(): array
    {
        // RPC unary courts — pas de PollActivityTaskQueue (long poll vs timeout client).
        $opts = $this->grpcTimeoutOptions(10.0);
        try {
            $this->rpc->workflowUnary(
                'GetSystemInfo',
                new GetSystemInfoRequest(),
                GetSystemInfoResponse::class,
                $opts,
            );

            return ['ok' => true];
        } catch (\Throwable $e1) {
            try {
                $this->rpc->workflowUnary(
                    'GetClusterInfo',
                    new GetClusterInfoRequest(),
                    GetClusterInfoResponse::class,
                    $opts,
                );

                return ['ok' => true];
            } catch (\Throwable $e2) {
                return [
                    'ok' => false,
                    'error' => sprintf(
                        'GetSystemInfo: %s | GetClusterInfo: %s',
                        $e1->getMessage(),
                        $e2->getMessage(),
                    ),
                ];
            }
        }
    }

    public function startWorkflow(string $workflowType, array $input = [], array $options = []): array
    {
        $taskQueue = $options['taskQueue'] ?? $options['task_queue'] ?? 'default';
        $wfId = $options['workflowId'] ?? $options['workflow_id'] ?? Uuid::v4()->toRfc4122();
        $typeName = $options['workflowTypeName'] ?? $this->resolveWorkflowTypeName($workflowType);

        $req = new StartWorkflowExecutionRequest();
        $req->setNamespace($this->namespace);
        $req->setWorkflowId($wfId);
        $req->setWorkflowType(new WorkflowType(['name' => $typeName]));
        $req->setTaskQueue(new TaskQueue(['name' => $taskQueue]));
        $req->setRequestId(Uuid::v4()->toRfc4122());
        $req->setIdentity($this->identity);
        $req->setInput($this->mapper->payloadsFromAssociativeInput($input));

        $timeout = $this->grpcTimeoutOptions(30.0);
        /** @var StartWorkflowExecutionResponse $resp */
        $resp = $this->rpc->workflowUnary('StartWorkflowExecution', $req, StartWorkflowExecutionResponse::class, $timeout);

        return [
            'workflowId' => $wfId,
            'runId' => $resp->getRunId(),
        ];
    }

    /**
     * Interroge une fois l’historique : résultat décodé si le workflow est en état « completed », sinon null.
     *
     * Utile pour entrelacer des passes worker (ex. {@see \Kiboko\Temporal\Testing\MessengerWorkerTicker::tick})
     * et l’attente du résultat dans un **même** processus PHP (sans sous-processus worker).
     */
    public function tryGetCompletedWorkflowResult(string $workflowId, string $runId): mixed
    {
        $req = new GetWorkflowExecutionHistoryRequest([
            'namespace' => $this->namespace,
            'execution' => new WorkflowExecution([
                'workflow_id' => $workflowId,
                'run_id' => $runId,
            ]),
            'maximum_page_size' => 200,
        ]);

        $resp = $this->rpc->workflowUnary(
            'GetWorkflowExecutionHistory',
            $req,
            GetWorkflowExecutionHistoryResponse::class,
            $this->grpcTimeoutOptions(15.0),
        );

        return $this->extractCompletedResult($resp);
    }

    public function getWorkflowResult(string $workflowId, string $runId): mixed
    {
        $deadline = microtime(true) + 60.0;

        while (microtime(true) < $deadline) {
            $result = $this->tryGetCompletedWorkflowResult($workflowId, $runId);
            if ($result !== null) {
                return $result;
            }

            usleep(150_000);
        }

        throw new \RuntimeException('Timeout en attendant le résultat du workflow Temporal.');
    }

    public function pollActivityTaskQueue(string $taskQueue, ?float $timeoutSeconds = null): ?array
    {
        $req = new PollActivityTaskQueueRequest();
        $req->setNamespace($this->namespace);
        $req->setTaskQueue(new TaskQueue(['name' => $taskQueue]));
        $req->setIdentity($this->identity);

        /** @var PollActivityTaskQueueResponse $resp */
        $resp = $this->rpc->workflowUnary(
            'PollActivityTaskQueue',
            $req,
            PollActivityTaskQueueResponse::class,
            $this->grpcTimeoutOptions($timeoutSeconds ?? 70.0),
        );

        if ($resp->getTaskToken() === '') {
            return null;
        }

        $input = $this->mapper->payloadsToInputArray($resp->getInput());
        $we = $resp->getWorkflowExecution();

        return [
            'taskToken' => base64_encode($resp->getTaskToken()),
            'activityType' => $resp->getActivityType()->getName(),
            'input' => $input,
            'workflowId' => $we !== null ? $we->getWorkflowId() : '',
            'runId' => $we !== null ? $we->getRunId() : '',
        ];
    }

    public function respondActivityTaskCompleted(string $taskToken, mixed $result): void
    {
        $rawToken = base64_decode($taskToken, true);
        if ($rawToken === false) {
            $rawToken = $taskToken;
        }

        $req = new RespondActivityTaskCompletedRequest([
            'task_token' => $rawToken,
            'result' => $this->mapper->payloadsFromScalar($result),
            'identity' => $this->identity,
            'namespace' => $this->namespace,
        ]);

        $this->rpc->workflowUnary(
            'RespondActivityTaskCompleted',
            $req,
            \Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedResponse::class,
            $this->grpcTimeoutOptions(20.0),
        );
    }

    public function respondActivityTaskFailed(string $taskToken, string $failure): void
    {
        $rawToken = base64_decode($taskToken, true);
        if ($rawToken === false) {
            $rawToken = $taskToken;
        }

        $req = new RespondActivityTaskFailedRequest([
            'task_token' => $rawToken,
            'failure' => new \Temporal\Api\Failure\V1\Failure(['message' => $failure]),
            'identity' => $this->identity,
            'namespace' => $this->namespace,
        ]);

        $this->rpc->workflowUnary(
            'RespondActivityTaskFailed',
            $req,
            \Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedResponse::class,
            $this->grpcTimeoutOptions(20.0),
        );
    }

    public function pollWorkflowTaskQueue(string $taskQueue, ?float $timeoutSeconds = null): ?PollWorkflowTaskQueueResponse
    {
        $req = new PollWorkflowTaskQueueRequest();
        $req->setNamespace($this->namespace);
        $req->setTaskQueue(new TaskQueue(['name' => $taskQueue]));
        $req->setIdentity($this->identity . '-workflow');

        /** @var PollWorkflowTaskQueueResponse $resp */
        $resp = $this->rpc->workflowUnary(
            'PollWorkflowTaskQueue',
            $req,
            PollWorkflowTaskQueueResponse::class,
            $this->grpcTimeoutOptions($timeoutSeconds ?? 70.0),
        );

        if ($resp->getTaskToken() === '') {
            return null;
        }

        return $resp;
    }

    public function respondWorkflowTaskCompleted(RespondWorkflowTaskCompletedRequest $request): void
    {
        $this->rpc->workflowUnary(
            'RespondWorkflowTaskCompleted',
            $request,
            \Temporal\Api\Workflowservice\V1\RespondWorkflowTaskCompletedResponse::class,
            $this->grpcTimeoutOptions(30.0),
        );
    }

    /**
     * @return array{timeout: int}
     */
    private function grpcTimeoutOptions(float $seconds): array
    {
        return ['timeout' => (int) ($seconds * 1_000_000)];
    }

    private function resolveWorkflowTypeName(string $workflowType): string
    {
        if (str_contains($workflowType, '\\')) {
            $base = basename(str_replace('\\', '/', $workflowType));

            return $base !== '' ? $base : $workflowType;
        }

        return $workflowType;
    }

    private function extractCompletedResult(GetWorkflowExecutionHistoryResponse $resp): mixed
    {
        $history = $resp->getHistory();
        if ($history === null) {
            return null;
        }

        foreach ($history->getEvents() as $event) {
            if (!$event instanceof HistoryEvent) {
                continue;
            }
            if ($event->getEventType() !== EventType::EVENT_TYPE_WORKFLOW_EXECUTION_COMPLETED) {
                continue;
            }
            $attrs = $event->getWorkflowExecutionCompletedEventAttributes();
            if ($attrs === null) {
                return null;
            }
            $payloads = $attrs->getResult();

            return $this->decodeWorkflowResultPayloads($payloads);
        }

        return null;
    }

    private function decodeWorkflowResultPayloads(?\Temporal\Api\Common\V1\Payloads $payloads): mixed
    {
        if ($payloads === null) {
            return null;
        }
        $count = \count($payloads->getPayloads());
        if ($count === 0) {
            return null;
        }
        if ($count === 1) {
            $arr = $this->mapper->payloadsToInputArray($payloads);

            return $arr['value'] ?? $arr['input'] ?? $arr;
        }

        $out = [];
        foreach ($payloads->getPayloads() as $p) {
            $out[] = $this->mapper->decodePayloadToArray($p);
        }

        return $out;
    }
}
