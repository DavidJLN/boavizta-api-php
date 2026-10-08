<?php

declare(strict_types=1);

namespace Boavizta\Api\Tests\Contract\Support;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Serves one recorded response, offline, after checking the client sent the recorded request.
 */
final class ReplayClient implements ClientInterface
{
    private bool $used = false;

    public function __construct(private readonly Exchange $exchange)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        Assert::assertFalse($this->used, 'The scenario sent more than the single recorded request');
        $this->used = true;

        $this->exchange->assertSameRequest($request, '');

        return new Response($this->exchange->status, ['Content-Type' => $this->exchange->contentType], $this->exchange->responseBody);
    }
}
