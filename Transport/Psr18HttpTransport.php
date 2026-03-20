<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Transport;

use Kiboko\Temporal\Serialization\TemporalPayloadCodecInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Transport HTTP vers l’API Temporal (port 7243) — **PSR-18** + **PSR-17** uniquement (pas de Symfony).
 *
 * Squelette : endpoints exacts selon version Temporal (Nexus / HTTP API).
 * Le timeout de long poll dépend du client injecté (non couvert par PSR-18).
 */
final class Psr18HttpTransport implements TransportInterface
{
    private const DEFAULT_BASE_URI = 'http://localhost:7243';

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $baseUri = self::DEFAULT_BASE_URI,
        private readonly string $namespace = 'default',
        private readonly ?TemporalPayloadCodecInterface $payloadCodec = null,
    ) {
    }

    public function ping(): bool
    {
        // Ne pas s’appuyer sur poll() : en cas d’erreur réseau il renvoie null sans lever
        // d’exception, ce qui faussait la détection « Temporal disponible » dans les tests.
        try {
            $uri = rtrim($this->baseUri, '/').'/';
            $request = $this->requestFactory->createRequest('GET', $uri);
            $response = $this->httpClient->sendRequest($request);

            return $response->getStatusCode() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function startWorkflow(string $workflowType, array $input = [], array $options = []): array
    {
        throw new \BadMethodCallException('Psr18HttpTransport::startWorkflow() non implémenté');
    }

    public function getWorkflowResult(string $workflowId, string $runId): mixed
    {
        throw new \BadMethodCallException('Psr18HttpTransport::getWorkflowResult() non implémenté');
    }

    public function pollActivityTaskQueue(string $taskQueue, ?float $timeoutSeconds = null): ?array
    {
        $url = sprintf(
            '%s/api/v1/namespaces/%s/task-queues/%s/activities/poll',
            rtrim($this->baseUri, '/'),
            $this->namespace,
            $taskQueue,
        );

        try {
            $request = $this->requestFactory->createRequest('POST', $url)
                ->withHeader('Accept', 'application/json')
                ->withHeader('Content-Type', 'application/json');

            $response = $this->httpClient->sendRequest($request);
            $status = $response->getStatusCode();

            if ($status === 204 || $status === 404) {
                return null;
            }

            $body = $response->getBody();
            if ($body->isSeekable()) {
                $body->rewind();
            }
            $raw = $body->getContents();
            if ($raw === '') {
                return null;
            }

            /** @var array<string, mixed> $data */
            $data = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
            $input = $data['input'] ?? [];

            if ($this->payloadCodec !== null && \is_array($input) && isset($input['data']) && \is_string($input['data'])) {
                try {
                    $decoded = $this->payloadCodec->fromTemporalWireArray($input);
                    $input = \is_array($decoded) ? $decoded : ['value' => $decoded];
                } catch (\Throwable) {
                }
            }

            return [
                'taskToken' => $data['taskToken'] ?? '',
                'activityType' => $data['activityType'] ?? '',
                'input' => $input,
                'workflowId' => $data['workflowExecution']['workflowId'] ?? '',
                'runId' => $data['workflowExecution']['runId'] ?? '',
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public function respondActivityTaskCompleted(string $taskToken, mixed $result): void
    {
        $url = sprintf(
            '%s/api/v1/namespaces/%s/activities/complete',
            rtrim($this->baseUri, '/'),
            $this->namespace,
        );

        $body = [
            'taskToken' => $taskToken,
            'result' => $this->payloadCodec !== null
                ? $this->payloadCodec->toTemporalWireArray($result)
                : $result,
        ];

        $stream = $this->streamFactory->createStream(json_encode($body, \JSON_THROW_ON_ERROR));
        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream);

        $this->httpClient->sendRequest($request);
    }

    public function respondActivityTaskFailed(string $taskToken, string $failure): void
    {
        $url = sprintf(
            '%s/api/v1/namespaces/%s/activities/fail',
            rtrim($this->baseUri, '/'),
            $this->namespace,
        );

        $payload = json_encode([
            'taskToken' => $taskToken,
            'failure' => ['message' => $failure],
        ], \JSON_THROW_ON_ERROR);

        $stream = $this->streamFactory->createStream($payload);
        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream);

        $this->httpClient->sendRequest($request);
    }
}
