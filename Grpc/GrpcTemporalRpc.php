<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Grpc;

use Google\Protobuf\Internal\Message;

/**
 * Appels gRPC unary vers le WorkflowService Temporal (sans SDK Temporal).
 *
 * Si un {@see GrpcUnaryInvokerInterface} est injecté (tests / doubles), l’extension **grpc** et la cible
 * réseau ne sont pas utilisées pour ces appels.
 */
final class GrpcTemporalRpc
{
    private readonly GrpcUnaryInvokerInterface $invoker;

    /**
     * @param non-empty-string $target Cible `host:port` — ignorée lorsque {@see $customInvoker} est fourni.
     */
    public function __construct(
        string $target,
        private readonly string $defaultIdentity = 'temporal-php',
        private readonly ?GrpcUnaryRetryPolicy $unaryRetryPolicy = null,
        ?GrpcUnaryInvokerInterface $customInvoker = null,
        ?TemporalGrpcTlsConfig $tlsConfig = null,
    ) {
        if ($customInvoker !== null) {
            $this->invoker = $customInvoker;

            return;
        }

        if (!\extension_loaded('grpc')) {
            throw new \RuntimeException('L’extension PHP grpc est requise pour parler à Temporal en gRPC (ou fournir un GrpcUnaryInvokerInterface).');
        }

        $tls = $tlsConfig ?? TemporalGrpcTlsConfig::fromEnvironment();
        $channel = GrpcChannelFactory::createChannel($target, $tls);
        $this->invoker = new DefaultGrpcUnaryInvoker($channel);
    }

    public function getDefaultIdentity(): string
    {
        return $this->defaultIdentity;
    }

    /**
     * @template T of Message
     *
     * @param class-string<T> $responseClass
     * @param array{timeout?: int} $options timeout en microsecondes pour l’appel gRPC
     *
     * @return T
     */
    public function workflowUnary(string $methodSuffix, Message $request, string $responseClass, array $options = []): Message
    {
        if ($this->unaryRetryPolicy === null) {
            return $this->invoker->invokeUnary($methodSuffix, $request, $responseClass, $options);
        }

        return GrpcUnaryRetryRunner::runWithRetry(
            fn () => $this->invoker->invokeUnary($methodSuffix, $request, $responseClass, $options),
            $this->unaryRetryPolicy,
        );
    }
}
