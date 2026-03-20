<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Grpc;

/**
 * Configuration TLS/mTLS pour le canal gRPC vers Temporal (fichiers PEM lus depuis le disque).
 *
 * Variables d’environnement (optionnelles, toutes absentes → connexion **insecure**) :
 * - `POC_TEMPORAL_GRPC_TLS_ROOT_CA_FILE` : PEM autorité(s) racine
 * - `POC_TEMPORAL_GRPC_TLS_CLIENT_CERT_FILE` : PEM certificat client
 * - `POC_TEMPORAL_GRPC_TLS_CLIENT_KEY_FILE` : PEM clé privée client
 */
final class TemporalGrpcTlsConfig
{
    public function __construct(
        public readonly bool $useTls = false,
        public readonly ?string $rootCertificatesPem = null,
        public readonly ?string $clientPrivateKeyPem = null,
        public readonly ?string $clientCertificateChainPem = null,
    ) {
    }

    public static function insecure(): self
    {
        return new self(useTls: false);
    }

    public static function fromEnvironment(): self
    {
        $root = self::readOptionalPemFile('POC_TEMPORAL_GRPC_TLS_ROOT_CA_FILE');
        $cert = self::readOptionalPemFile('POC_TEMPORAL_GRPC_TLS_CLIENT_CERT_FILE');
        $key = self::readOptionalPemFile('POC_TEMPORAL_GRPC_TLS_CLIENT_KEY_FILE');

        if ($root === null && $cert === null && $key === null) {
            return new self(useTls: false);
        }

        return new self(
            useTls: true,
            rootCertificatesPem: $root,
            clientPrivateKeyPem: $key,
            clientCertificateChainPem: $cert,
        );
    }

    private static function readOptionalPemFile(string $envName): ?string
    {
        $path = getenv($envName);
        if (false === $path || '' === $path) {
            return null;
        }

        if (!is_readable($path)) {
            throw new \RuntimeException(sprintf('[%s] Fichier PEM illisible ou absent : %s', $envName, $path));
        }

        $contents = file_get_contents($path);
        if (false === $contents) {
            throw new \RuntimeException(sprintf('[%s] Lecture impossible : %s', $envName, $path));
        }

        return $contents;
    }
}
