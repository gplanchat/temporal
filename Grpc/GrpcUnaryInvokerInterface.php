<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Grpc;

use Google\Protobuf\Internal\Message;

/**
 * Une tentative d’appel **unary** vers le WorkflowService Temporal (réseau réel ou double de test).
 */
interface GrpcUnaryInvokerInterface
{
    /**
     * @template T of Message
     *
     * @param class-string<T> $responseClass
     * @param array{timeout?: int} $options
     *
     * @return T
     */
    public function invokeUnary(string $methodSuffix, Message $request, string $responseClass, array $options = []): Message;
}
