<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Grpc;

use Google\Protobuf\Internal\Message;

/**
 * Invocation unary via {@see \Grpc\UnaryCall} (extension grpc requise).
 */
final class DefaultGrpcUnaryInvoker implements GrpcUnaryInvokerInterface
{
    private const WORKFLOW_SERVICE = '/temporal.api.workflowservice.v1.WorkflowService';

    public function __construct(
        private readonly \Grpc\Channel $channel,
    ) {
    }

    public function invokeUnary(string $methodSuffix, Message $request, string $responseClass, array $options = []): Message
    {
        if (!\extension_loaded('grpc')) {
            throw new \RuntimeException('L’extension PHP grpc est requise pour DefaultGrpcUnaryInvoker.');
        }

        $fullMethod = self::WORKFLOW_SERVICE.'/'.$methodSuffix;
        /** @var array{0: class-string<Message>, 1: 'decode'} $deserialize */
        $deserialize = [$responseClass, 'decode'];

        $call = new \Grpc\UnaryCall($this->channel, $fullMethod, $deserialize, $options);
        $call->start($request, [], []);
        /** @var array{0: ?Message, 1: object} $pair */
        $pair = $call->wait();
        [$response, $status] = $pair;

        $code = (int) ($status->code ?? 0);
        if ($code !== \Grpc\STATUS_OK) {
            throw new GrpcUnaryCallFailedException(
                \sprintf(
                    'Temporal gRPC %s a échoué : %s (code %s)',
                    $methodSuffix,
                    $status->details ?? '',
                    (string) $code,
                ),
                $code,
                $methodSuffix,
            );
        }

        if (!$response instanceof Message) {
            throw new \RuntimeException('Réponse gRPC vide pour '.$methodSuffix);
        }

        return $response;
    }
}
