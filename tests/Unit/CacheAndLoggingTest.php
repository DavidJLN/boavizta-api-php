<?php

declare(strict_types=1);

namespace Boavizta\Api\Tests\Unit;

use Boavizta\Api\BoaviztaClient;
use Boavizta\Api\Dto\Cpu;
use Boavizta\Api\Exception\NotFoundException;
use Boavizta\Api\Exception\TransportException;
use Boavizta\Api\Tests\Unit\Support\ArrayCachePool;
use Boavizta\Api\Tests\Unit\Support\CollectingLogger;
use Boavizta\Api\Tests\Unit\Support\FailingCachePool;
use GuzzleHttp\Psr7\HttpFactory;
use Http\Client\Exception\NetworkException;
use Psr\Http\Message\RequestInterface;

final class CacheAndLoggingTest extends MockClientTestCase
{
    private ArrayCachePool $cache;
    private CollectingLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new ArrayCachePool();
        $this->logger = new CollectingLogger();
        $factory = new HttpFactory();
        $this->client = BoaviztaClient::create('http://boavizta.test', $this->http, $factory, $factory, $this->cache, $this->logger, 600);
    }

    public function testGetResponsesAreCached(): void
    {
        $this->queueJson('2.4.1');
        $this->queueJson(['dellR740', 'hpe_dl360']);

        $first = $this->client->server()->archetypes();
        $second = $this->client->server()->archetypes();

        self::assertSame(['dellR740', 'hpe_dl360'], $second);
        self::assertSame($first, $second);

        self::assertSame(['/v1/utils/version', '/v1/server/archetypes'], $this->requestedPaths());
        self::assertCount(1, $this->cache->items);
        self::assertSame(600, array_values($this->cache->items)[0]->ttl);
    }

    public function testCacheIsOffByDefault(): void
    {
        $factory = new HttpFactory();
        $client = BoaviztaClient::create('http://boavizta.test', $this->http, $factory, $factory);
        $this->queueJson(['dellR740']);
        $this->queueJson(['dellR740']);

        $client->server()->archetypes();
        $client->server()->archetypes();

        self::assertSame(['/v1/server/archetypes', '/v1/server/archetypes'], $this->requestedPaths(), 'No cache, so no version request either');
    }

    public function testCacheKeyContainsTheApiVersion(): void
    {
        $this->queueJson('2.4.1');
        $this->queueJson(['dellR740']);
        $this->client->server()->archetypes();

        // Same pool, server upgraded: the response cached in the old format must not be served.
        $upgraded = $this->newClientOnSamePool();
        $this->queueJson('2.5.0');
        $this->queueJson(['dellR740', 'new-format']);
        self::assertSame(['dellR740', 'new-format'], $upgraded->server()->archetypes());

        // Same pool, same version: served from cache.
        $sameVersion = $this->newClientOnSamePool();
        $this->queueJson('2.4.1');
        self::assertSame(['dellR740'], $sameVersion->server()->archetypes());

        self::assertSame(
            ['/v1/utils/version', '/v1/server/archetypes', '/v1/utils/version', '/v1/server/archetypes', '/v1/utils/version'],
            $this->requestedPaths(),
        );
        self::assertCount(2, $this->cache->items);
    }

    public function testVersionIsAskedOncePerClientAndNeverCached(): void
    {
        $this->queueJson('2.4.1');
        $this->queueJson(['dellR740']);
        $this->queueJson(['aws']);
        $this->queueJson('2.4.1');

        $this->client->server()->archetypes();
        $this->client->cloud()->providers();
        $this->client->utils()->version();

        self::assertSame(['/v1/utils/version', '/v1/server/archetypes', '/v1/cloud/instance/all_providers', '/v1/utils/version'], $this->requestedPaths());
        self::assertCount(2, $this->cache->items);
    }

    public function testFailingCacheIsLoggedNotThrown(): void
    {
        $factory = new HttpFactory();
        $client = BoaviztaClient::create('http://boavizta.test', $this->http, $factory, $factory, new FailingCachePool(), $this->logger);
        $this->queueJson('2.4.1');
        $this->queueJson(['dellR740']);

        self::assertSame(['dellR740'], $client->server()->archetypes());
        self::assertContains('warning', array_column($this->logger->records, 'level'));
    }

    public function testPostResponsesAreNotCached(): void
    {
        $this->queueJson(['impacts' => []]);
        $this->queueJson(['impacts' => []]);

        $this->client->component()->impact(new Cpu(units: 1));
        $this->client->component()->impact(new Cpu(units: 1));

        self::assertCount(2, $this->http->getRequests());
        self::assertSame([], $this->cache->items);
    }

    public function testErrorsAreNotCached(): void
    {
        $this->queueJson('2.4.1');
        $this->queueJson(['detail' => 'not found'], 404);
        $this->queueJson(['detail' => 'not found'], 404);

        foreach ([1, 2] as $_) {
            try {
                $this->client->server()->archetypeConfig('unknown');
                self::fail('NotFoundException expected');
            } catch (NotFoundException) {
            }
        }

        self::assertCount(3, $this->http->getRequests());
        self::assertSame([], $this->cache->items);
    }

    public function testCallsAreLogged(): void
    {
        $this->queueJson('2.4.1');
        $this->queueJson(['dellR740']);
        $this->client->server()->archetypes();
        $this->client->server()->archetypes();

        self::assertSame(['debug', 'debug', 'debug'], array_column($this->logger->records, 'level'));
        self::assertSame(200, $this->logger->records[1]['context']['status']);
        self::assertStringContainsString('served from cache', $this->logger->records[2]['message']);
    }

    public function testHttpErrorsAreLoggedAsWarnings(): void
    {
        $this->queueJson(['detail' => 'boom'], 500);

        try {
            $this->client->utils()->version();
        } catch (\Throwable) {
        }

        self::assertSame('warning', $this->logger->records[0]['level']);
        self::assertSame(500, $this->logger->records[0]['context']['status']);
    }

    private function newClientOnSamePool(): BoaviztaClient
    {
        $factory = new HttpFactory();

        return BoaviztaClient::create('http://boavizta.test', $this->http, $factory, $factory, $this->cache, $this->logger, 600);
    }

    /**
     * @return list<string>
     */
    private function requestedPaths(): array
    {
        return array_values(array_map(static fn (RequestInterface $r): string => $r->getUri()->getPath(), $this->http->getRequests()));
    }

    public function testNetworkFailuresAreLoggedAsErrors(): void
    {
        $this->http->addException(new NetworkException('connection refused', (new HttpFactory())->createRequest('GET', '/')));

        try {
            $this->client->utils()->version();
            self::fail('TransportException expected');
        } catch (TransportException) {
        }

        self::assertSame('error', $this->logger->records[0]['level']);
        self::assertInstanceOf(NetworkException::class, $this->logger->records[0]['context']['exception']);
    }
}
