<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Grpc;

/**
 * Échec d’un appel gRPC unary avec code de statut {@see https://grpc.github.io/grpc/core/md_doc_statuscodes.html}.
 */
final class GrpcUnaryCallFailedException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $grpcStatusCode,
        public readonly string $methodSuffix,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
