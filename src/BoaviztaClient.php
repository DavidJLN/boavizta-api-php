<?php

declare(strict_types=1);

namespace Boavizta\Api;

use Boavizta\Api\Exception\ConfigurationException;
use Boavizta\Api\Http\HttpClientFactory;
use Boavizta\Api\Http\Transport;
use Boavizta\Api\Resource\CloudResource;
use Boavizta\Api\Resource\ComponentResource;
use Boavizta\Api\Resource\ConsumptionProfileResource;
use Boavizta\Api\Resource\IotResource;
use Boavizta\Api\Resource\PeripheralResource;
use Boavizta\Api\Resource\ServerResource;
use Boavizta\Api\Resource\TerminalResource;
use Boavizta\Api\Resource\UtilsResource;
use Http\Discovery\Exception\NotFoundException as DiscoveryNotFoundException;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Entry point of the BoaviztAPI client.
 *
 *     $client = BoaviztaClient::create();
 *     $result = $client->cloud()->instanceImpact('aws', 'a1.4xlarge');
 */
final class BoaviztaClient
{
    public const DEFAULT_BASE_URI = 'https://api.boavizta.org';

    /**
     * Seconds. Long enough for the slowest computations (verbose cloud impacts), short enough
     * that a server outage does not hold the application's workers.
     */
    public const DEFAULT_TIMEOUT = 30.0;

    private ?ServerResource $server = null;
    private ?CloudResource $cloud = null;
    private ?ComponentResource $component = null;
    private ?TerminalResource $terminal = null;
    private ?PeripheralResource $peripheral = null;
    private ?IotResource $iot = null;
    private ?ConsumptionProfileResource $consumptionProfile = null;
    private ?UtilsResource $utils = null;

    public function __construct(private readonly Transport $transport)
    {
    }

    /**
     * Builds a client; any PSR-18 client / PSR-17 factory installed is auto-discovered when not given.
     *
     * @param ClientInterface|null        $httpClient Your PSR-18 client, with your own timeout: `$timeout` does not apply to it.
     * @param CacheItemPoolInterface|null $cache      Optional PSR-6 pool caching GET responses; off when null.
     * @param LoggerInterface|null        $logger     Optional PSR-3 logger for HTTP calls and errors.
     * @param int|null                    $cacheTtl   Lifetime in seconds of cached responses; null lets the pool decide.
     * @param float|null                  $timeout    Seconds, for the client built when `$httpClient` is null (Symfony
     *                                                HttpClient, Guzzle or php-http/curl-client); null for none.
     *
     * @throws ConfigurationException When no client or factory is installed, or the timeout cannot be set.
     */
    public static function create(
        string $baseUri = self::DEFAULT_BASE_URI,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?CacheItemPoolInterface $cache = null,
        ?LoggerInterface $logger = null,
        ?int $cacheTtl = Transport::DEFAULT_CACHE_TTL,
        ?float $timeout = self::DEFAULT_TIMEOUT,
    ): self {
        try {
            $requestFactory ??= Psr17FactoryDiscovery::findRequestFactory();
            $streamFactory ??= Psr17FactoryDiscovery::findStreamFactory();
        } catch (DiscoveryNotFoundException $e) {
            throw new ConfigurationException('No PSR-17 factories found: install some (e.g. composer require nyholm/psr7) or pass yours to BoaviztaClient::create()', 0, null, $e);
        }

        return new self(new Transport(
            $baseUri,
            $httpClient ?? HttpClientFactory::create($timeout),
            $requestFactory,
            $streamFactory,
            $cache,
            $logger,
            $cacheTtl,
        ));
    }

    public function server(): ServerResource
    {
        return $this->server ??= new ServerResource($this->transport);
    }

    public function cloud(): CloudResource
    {
        return $this->cloud ??= new CloudResource($this->transport);
    }

    public function component(): ComponentResource
    {
        return $this->component ??= new ComponentResource($this->transport);
    }

    public function terminal(): TerminalResource
    {
        return $this->terminal ??= new TerminalResource($this->transport);
    }

    public function peripheral(): PeripheralResource
    {
        return $this->peripheral ??= new PeripheralResource($this->transport);
    }

    public function iot(): IotResource
    {
        return $this->iot ??= new IotResource($this->transport);
    }

    public function consumptionProfile(): ConsumptionProfileResource
    {
        return $this->consumptionProfile ??= new ConsumptionProfileResource($this->transport);
    }

    public function utils(): UtilsResource
    {
        return $this->utils ??= new UtilsResource($this->transport);
    }
}
