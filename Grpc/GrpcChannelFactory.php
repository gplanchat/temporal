<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Grpc;

/**
 * Fabrique de {@see \Grpc\Channel} : insecure ou TLS/mTLS via {@see \Grpc\ChannelCredentials::createSsl}.
 */
final class GrpcChannelFactory
{
    public static function createChannel(string $target, TemporalGrpcTlsConfig $tls): \Grpc\Channel
    {
        if (!$tls->useTls) {
            return new \Grpc\Channel($target, [
                'credentials' => \Grpc\ChannelCredentials::createInsecure(),
            ]);
        }

        $credentials = \Grpc\ChannelCredentials::createSsl(
            $tls->rootCertificatesPem,
            $tls->clientPrivateKeyPem,
            $tls->clientCertificateChainPem,
        );

        return new \Grpc\Channel($target, [
            'credentials' => $credentials,
        ]);
    }
}
