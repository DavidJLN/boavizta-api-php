<?php

declare(strict_types=1);

namespace Boavizta\Api\Tests\Unit;

use Boavizta\Api\BoaviztaClient;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Http\Mock\Client as MockClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

abstract class MockClientTestCase extends TestCase
{
    protected MockClient $http;
    protected BoaviztaClient $client;

    protected function setUp(): void
    {
        $this->http = new MockClient();
        $factory = new HttpFactory();
        $this->client = BoaviztaClient::create('http://boavizta.test/', $this->http, $factory, $factory);
    }

    protected function queueJson(mixed $body, int $status = 200): void
    {
        $this->http->addResponse(new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR)));
    }

    protected function lastRequest(): RequestInterface
    {
        $request = $this->http->getLastRequest();
        self::assertNotFalse($request);

        return $request;
    }

    /**
     * @return array<mixed>
     */
    protected function lastJsonBody(): array
    {
        return json_decode((string) $this->lastRequest()->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
