<?php

declare(strict_types=1);

namespace Turanjanin\FiscalReceipts\Tests\Fakes;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class FakeClient implements ClientInterface
{
    public array $receivedRequests = [];

    public array $responses = [];

    public function addResponse(UriInterface $uri, ResponseInterface $response): void
    {
        $this->responses[(string)$uri] = $response;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->receivedRequests[] = $request->getUri();

        $key = (string)$request->getUri();

        if (!isset($this->responses[$key])) {
            throw new \RuntimeException("Unexpected request received: " . $key);
        }

        return $this->responses[$key];
    }
}
