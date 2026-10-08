<?php

declare(strict_types=1);

namespace Boavizta\Api\Tests\Contract\Support;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Decorates a real PSR-18 client and keeps every exchange it goes through.
 */
final class RecordingClient implements ClientInterface
{
    /** @var list<Exchange> */
    public array $exchanges = [];

    public function __construct(
        private readonly ClientInterface $inner,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $basePath = '',
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $response = $this->inner->sendRequest($request);
        $body = (string) $response->getBody();

        $this->exchanges[] = Exchange::fromPsr($request, $this->basePath, $response->getStatusCode(), $response->getHeaderLine('Content-Type'), $body);

        return $response->withBody($this->streamFactory->createStream($body));
    }

    public function lastExchange(): Exchange
    {
        return $this->exchanges[array_key_last($this->exchanges)] ?? throw new \LogicException('No request was sent');
    }
}
