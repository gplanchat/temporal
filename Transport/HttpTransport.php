<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Transport;

use Kiboko\Temporal\Http\HttpTransientRetryPolicy;
use Kiboko\Temporal\Http\RetryingPsr18Client;
use Kiboko\Temporal\Serialization\TemporalPayloadCodecInterface;
use Kiboko\Temporal\Transport\Psr18HttpTransport;
use Kiboko\Temporal\Transport\TransportInterface;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Adaptateur Symfony HttpClient → {@see Psr18HttpTransport} (core PSR-18).
 */
final class HttpTransport implements TransportInterface
{
    private readonly Psr18HttpTransport $inner;

    private const DEFAULT_BASE_URI = 'http://localhost:7243';

    public function __construct(
        HttpClientInterface $httpClient,
        string $baseUri = self::DEFAULT_BASE_URI,
        string $namespace = 'default',
        ?TemporalPayloadCodecInterface $payloadCodec = null,
        ?HttpTransientRetryPolicy $httpUnaryRetryPolicy = null,
    ) {
        $psr18 = new Psr18Client($httpClient);
        $httpClientForSend = self::wrapPsr18WithOptionalRetry($psr18, $httpUnaryRetryPolicy);
        // Psr18Client fournit aussi RequestFactory + StreamFactory ; seul l’envoi est décoré pour le retry.
        $this->inner = new Psr18HttpTransport($httpClientForSend, $psr18, $psr18, $baseUri, $namespace, $payloadCodec);
    }

    /**
     * Même sémantique que {@see GrpcTransport::unaryRetryPolicyFromEnvironment()} pour `POC_TEMPORAL_HTTP_MAX_RETRIES`.
     */
    public static function httpUnaryRetryPolicyFromEnvironment(): ?HttpTransientRetryPolicy
    {
        return HttpTransientRetryPolicy::fromEnvironment();
    }

    private static function wrapPsr18WithOptionalRetry(
        ClientInterface $psr18,
        ?HttpTransientRetryPolicy $policy,
    ): ClientInterface {
        if ($policy === null || $policy->maxAttempts <= 1) {
            return $psr18;
        }

        return new RetryingPsr18Client($psr18, $policy);
    }

    public function ping(): bool
    {
        return $this->inner->ping();
    }

    public function startWorkflow(string $workflowType, array $input = [], array $options = []): array
    {
        return $this->inner->startWorkflow($workflowType, $input, $options);
    }

    public function getWorkflowResult(string $workflowId, string $runId): mixed
    {
        return $this->inner->getWorkflowResult($workflowId, $runId);
    }

    public function pollActivityTaskQueue(string $taskQueue, ?float $timeoutSeconds = null): ?array
    {
        return $this->inner->pollActivityTaskQueue($taskQueue, $timeoutSeconds);
    }

    public function respondActivityTaskCompleted(string $taskToken, mixed $result): void
    {
        $this->inner->respondActivityTaskCompleted($taskToken, $result);
    }

    public function respondActivityTaskFailed(string $taskToken, string $failure): void
    {
        $this->inner->respondActivityTaskFailed($taskToken, $failure);
    }
}
