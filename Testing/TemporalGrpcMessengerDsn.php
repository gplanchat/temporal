<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Testing;

/**
 * Construction des DSN Messenger {@see TemporalTransportFactory} pour Temporal en gRPC.
 *
 * Centralise {@see self::ENV_POLL_TIMEOUT} pour réutilisation hors kernel E2E (ex. application « prod »
 * du monorepo qui copierait les mêmes schémas d’URL).
 */
final class TemporalGrpcMessengerDsn
{
    /**
     * Durée max (secondes) d’un poll gRPC activité / workflow — nom prévu pour kernels « prod » / monorepo.
     */
    public const ENV_POLL_TIMEOUT = 'TEMPORAL_GRPC_POLL_TIMEOUT';

    /**
     * Alias historique PoC ; lu si {@see self::ENV_POLL_TIMEOUT} est absent ou vide.
     */
    public const ENV_POLL_TIMEOUT_LEGACY = 'POC_TEMPORAL_GRPC_POLL_TIMEOUT';

    public static function pollTimeoutQueryValue(): string
    {
        foreach ([self::ENV_POLL_TIMEOUT, self::ENV_POLL_TIMEOUT_LEGACY] as $name) {
            $v = getenv($name);
            if (false !== $v && $v !== '') {
                return $v;
            }
        }

        return '2';
    }

    /**
     * @param non-empty-string $temporalAddress ex. temporal:7233 ou 127.0.0.1:7233
     * @param non-empty-string $namespace      segment de chemin dans le DSN (souvent default)
     * @param non-empty-string $taskQueue      paramètre query task_queue
     */
    public static function activityTransportDsn(
        string $temporalAddress,
        string $namespace = 'default',
        string $taskQueue = 'default',
    ): string {
        [$host, $port] = IntegrationTransportFactory::parseGrpcAddress($temporalAddress);
        $poll = self::pollTimeoutQueryValue();

        return sprintf(
            'temporal+grpc://%s:%d/%s?task_queue=%s&poll_timeout=%s',
            $host,
            $port,
            $namespace,
            $taskQueue,
            $poll,
        );
    }

    /**
     * @param non-empty-string $temporalAddress
     * @param non-empty-string $namespace
     * @param non-empty-string $taskQueue
     */
    public static function workflowTransportDsn(
        string $temporalAddress,
        string $namespace = 'default',
        string $taskQueue = 'default',
    ): string {
        [$host, $port] = IntegrationTransportFactory::parseGrpcAddress($temporalAddress);
        $poll = self::pollTimeoutQueryValue();

        return sprintf(
            'temporal+grpc-workflow://%s:%d/%s?task_queue=%s&poll_timeout=%s',
            $host,
            $port,
            $namespace,
            $taskQueue,
            $poll,
        );
    }
}
