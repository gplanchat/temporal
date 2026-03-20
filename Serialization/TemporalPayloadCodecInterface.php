<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Serialization;

/**
 * Encode / décode les charges utiles pour Temporal (HTTP Nexus, gRPC Payload, etc.).
 *
 * @see https://github.com/temporalio/api/blob/master/temporal/api/common/v1/message.proto Payload
 */
interface TemporalPayloadCodecInterface
{
    public function encodeJson(mixed $value): string;

    /**
     * @template T of object
     * @param class-string<T>|null $type
     * @return ($type is class-string<T> ? T : array<string, mixed>)
     */
    public function decodeJson(string $json, ?string $type = null): mixed;

    /**
     * @return array{metadata: array<string, string>, data: string}
     */
    public function toTemporalWireArray(mixed $value): array;

    /**
     * @param array<string, mixed> $wire
     */
    public function fromTemporalWireArray(array $wire): mixed;

    public function toGrpcPayloadBytes(mixed $value): string;
}
