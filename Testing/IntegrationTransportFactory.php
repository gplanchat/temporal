<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Testing;

use Kiboko\Temporal\Grpc\TemporalPayloadMapper;
use Kiboko\Temporal\Serialization\TemporalPayloadCodecInterface;
use Kiboko\Temporal\Transport\GrpcTransport;
use Kiboko\Temporal\Transport\HttpTransport;
use Kiboko\Temporal\Transport\TransportInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Factory pour créer un transport à partir des variables d'environnement.
 * Utilisé par les tests d'intégration.
 */
final class IntegrationTransportFactory
{
    private const DEFAULT_TEMPORAL_ADDRESS = 'localhost:7233';
    private const DEFAULT_HTTP_PORT = 7243;

    public static function createHttpTransport(
        ?HttpClientInterface $httpClient = null,
        ?TemporalPayloadCodecInterface $payloadCodec = null,
    ): TransportInterface {
        $address = getenv('TEMPORAL_ADDRESS') ?: self::DEFAULT_TEMPORAL_ADDRESS;
        $httpAddress = getenv('TEMPORAL_HTTP_ADDRESS');
        $baseUri = $httpAddress !== false
            ? ('http://' . $httpAddress)
            : self::addressToHttpBaseUri($address);

        return new HttpTransport(
            $httpClient ?? HttpClient::create(),
            $baseUri,
            'default',
            $payloadCodec ?? self::defaultPayloadCodec(),
            HttpTransport::httpUnaryRetryPolicyFromEnvironment(),
        );
    }

    /**
     * Client Temporal via gRPC (extension `grpc` requise) — sans SDK Temporal.
     */
    public static function createGrpcTransport(
        ?TemporalPayloadCodecInterface $payloadCodec = null,
        string $namespace = 'default',
    ): TransportInterface {
        if (!extension_loaded('grpc')) {
            throw new \RuntimeException('L’extension PHP grpc est requise pour createGrpcTransport().');
        }

        $address = getenv('TEMPORAL_ADDRESS') ?: self::DEFAULT_TEMPORAL_ADDRESS;
        [$host, $port] = self::parseGrpcAddress($address);
        $codec = $payloadCodec ?? TemporalTestSerializerFactory::createTemporalPayloadCodec();
        $mapper = new TemporalPayloadMapper($codec);

        return GrpcTransport::createDefault(sprintf('%s:%d', $host, $port), $mapper, $namespace);
    }

    /**
     * @return array{0: string, 1: int}
     */
    public static function parseGrpcAddress(string $address): array
    {
        $parts = explode(':', $address, 2);
        $host = $parts[0] !== '' ? $parts[0] : '127.0.0.1';
        $port = isset($parts[1]) && $parts[1] !== '' ? (int) $parts[1] : 7233;

        return [$host, $port];
    }

    /**
     * Nécessite kiboko/temporal-bundle (classe {@see \Kiboko\TemporalBundle\Testing\TemporalTestSerializerFactory}) sauf si un codec est fourni.
     */
    private static function defaultPayloadCodec(): TemporalPayloadCodecInterface
    {
        if (!\class_exists(\Kiboko\TemporalBundle\Testing\TemporalTestSerializerFactory::class)) {
            throw new \LogicException(
                'TemporalPayloadCodecInterface requis : installez kiboko/temporal-bundle ou passez $payloadCodec explicitement.'
            );
        }

        return \Kiboko\TemporalBundle\Testing\TemporalTestSerializerFactory::createTemporalPayloadCodec();
    }

    private static function addressToHttpBaseUri(string $address): string
    {
        $parts = explode(':', $address);
        $host = $parts[0];
        $port = (int) ($parts[1] ?? self::DEFAULT_HTTP_PORT);

        if ($port === 7233) {
            $port = self::DEFAULT_HTTP_PORT;
        }

        return sprintf('http://%s:%d', $host, $port);
    }

    /**
     * null = Temporal considéré joignable pour les tests (gRPC si ext. chargée, sinon ping HTTP).
     * Chaîne = message à passer à {@see \PHPUnit\Framework\TestCase::markTestSkipped()}.
     */
    public static function explainTemporalSkipReason(): ?string
    {
        if (getenv('TEMPORAL_AVAILABLE') === '0') {
            return 'TEMPORAL_AVAILABLE=0';
        }

        if (extension_loaded('grpc')) {
            try {
                $transport = self::createGrpcTransport();
            } catch (\Throwable $e) {
                return 'Création transport gRPC : '.$e->getMessage();
            }

            if (!$transport instanceof GrpcTransport) {
                return 'Transport inattendu (pas GrpcTransport).';
            }

            $detail = $transport->pingDetailed();
            if ($detail['ok']) {
                return null;
            }

            return 'Ping gRPC échoué : '.$detail['error']
                .' — exécutez : docker compose exec php php bin/verify-temporal-stack.php'
                .' | TEMPORAL_ADDRESS='.var_export(getenv('TEMPORAL_ADDRESS') ?: 'non défini', true);
        }

        try {
            $http = self::createHttpTransport();
            if ($http->ping()) {
                return null;
            }

            return 'Sans extension grpc, le ping HTTP vers Temporal a échoué (API ~7243). Installez grpc ou démarrez la stack Docker.';
        } catch (\Throwable $e) {
            return 'Ping HTTP : '.$e->getMessage();
        }
    }
}
