<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Décorateur PSR-18 : retry sur statuts HTTP transitoires ({@see HttpTransientRetryPolicy}).
 */
final class RetryingPsr18Client implements ClientInterface
{
    public function __construct(
        private readonly ClientInterface $inner,
        private readonly HttpTransientRetryPolicy $policy,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if ($this->policy->maxAttempts <= 1) {
            return $this->inner->sendRequest($request);
        }

        return HttpPsr18RetryRunner::runWithRetry(function () use ($request): ResponseInterface {
            $body = $request->getBody();
            if ($body->isSeekable()) {
                $body->rewind();
            }

            $response = $this->inner->sendRequest($request);
            $code = $response->getStatusCode();
            if ($this->policy->isRetryableStatusCode($code)) {
                throw new HttpRetryableResponseException($code, 'HTTP '.$code.' — retry');
            }

            return $response;
        }, $this->policy);
    }
}
