<?php

declare(strict_types=1);

namespace Boavizta\Api\Http;

use Boavizta\Api\Exception\BoaviztaException;
use Boavizta\Api\Exception\ConfigurationException;
use Boavizta\Api\Exception\NotFoundException;
use Boavizta\Api\Exception\RateLimitException;
use Boavizta\Api\Exception\ServerException;
use Boavizta\Api\Exception\TransportException;
use Boavizta\Api\Exception\UnexpectedResponseException;
use Boavizta\Api\Exception\ValidationException;
use Psr\Cache\CacheException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException as CacheInvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Thin JSON layer over a PSR-18 client.
 *
 * The PSR-6 cache and PSR-3 logger are optional: psr/cache and psr/log only need to be
 * installed when an instance is actually given.
 *
 * Only exceptions of the Boavizta\Api\Exception hierarchy leave this class, whatever the PSR-18
 * client or PSR-6 pool throws. Nothing is ever retried here.
 */
final class Transport
{
    public const DEFAULT_CACHE_TTL = 86400;

    /**
     * The server does not announce its version in a header: this endpoint is the only way to know it.
     */
    public const VERSION_PATH = '/v1/utils/version';

    private readonly string $baseUri;

    /**
     * Version announced by the server, asked once per instance and only when a cache is set.
     */
    private ?string $apiVersion = null;

    /**
     * @param int|null $cacheTtl Lifetime in seconds of cached GET responses; null lets the pool decide.
     */
    public function __construct(
        string $baseUri,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ?CacheItemPoolInterface $cache = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?int $cacheTtl = self::DEFAULT_CACHE_TTL,
    ) {
        $this->baseUri = rtrim($baseUri, '/');

        // Let the PSR-17 implementation itself judge the URI now, rather than throw its own
        // exception type at the first call.
        $this->buildRequest('GET', $this->baseUri . self::VERSION_PATH, null);
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $query
     */
    public function get(string $path, array $query = []): mixed
    {
        return $this->send('GET', $path, $query);
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $query
     */
    public function post(string $path, mixed $body, array $query = []): mixed
    {
        return $this->send('POST', $path, $query, $body);
    }

    /**
     * FastAPI expects list parameters as repeated keys (`criteria=gwp&criteria=pe`),
     * which `http_build_query()` cannot produce.
     *
     * @param array<string, scalar|list<scalar>|null> $query
     */
    public static function buildQuery(array $query): string
    {
        $parts = [];
        foreach ($query as $key => $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                if ($item === null) {
                    continue;
                }
                if (is_bool($item)) {
                    $item = $item ? 'true' : 'false';
                }
                $parts[] = rawurlencode($key) . '=' . rawurlencode((string) $item);
            }
        }

        return implode('&', $parts);
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $query
     */
    private function send(string $method, string $path, array $query, mixed $body = null): mixed
    {
        $uri = $this->baseUri . '/' . ltrim($path, '/');
        $queryString = self::buildQuery($query);
        if ($queryString !== '') {
            $uri .= '?' . $queryString;
        }

        // Only GET responses are cached: they are idempotent and mostly reference data. The version
        // endpoint never is: it is what tells a cached response from a stale one.
        $cacheItem = $method === 'GET' && $this->cache !== null && $path !== self::VERSION_PATH
            ? $this->cacheItem($this->cacheKey($uri))
            : null;
        if ($cacheItem?->isHit()) {
            $this->logger?->debug('BoaviztAPI {method} {uri}: served from cache', ['method' => $method, 'uri' => $uri]);

            return $cacheItem->get();
        }

        $json = null;
        if ($method !== 'GET') {
            try {
                $json = json_encode($body ?? new \stdClass(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
            } catch (\JsonException $e) {
                throw new \InvalidArgumentException(sprintf('Cannot encode the body of %s %s as JSON: %s', $method, $uri, $e->getMessage()), 0, $e);
            }
        }
        $request = $this->buildRequest($method, $uri, $json);

        $start = hrtime(true);
        try {
            $response = $this->httpClient->sendRequest($request);
            $raw = $response->getBody()->getContents();
        } catch (ClientExceptionInterface|\RuntimeException $e) {
            // \RuntimeException: some clients stream the body, which can then fail while it is read.
            $this->logger?->error('BoaviztAPI {method} {uri} failed: {error}', [
                'method' => $method,
                'uri' => $uri,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            throw new TransportException(sprintf('%s %s failed: %s', $method, $uri, $e->getMessage()), 0, null, $e);
        }

        $status = $response->getStatusCode();
        $this->logger?->log($status >= 400 ? 'warning' : 'debug', 'BoaviztAPI {method} {uri} returned HTTP {status} in {duration_ms} ms', [
            'method' => $method,
            'uri' => $uri,
            'status' => $status,
            'duration_ms' => round((hrtime(true) - $start) / 1e6, 1),
        ]);

        try {
            $data = $raw === '' ? null : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            if ($status < 400) {
                throw new UnexpectedResponseException(sprintf('Invalid JSON returned by %s %s', $method, $uri), $status, null, $e);
            }
            $data = null;
        }

        if ($status < 400) {
            if ($cacheItem !== null) {
                $this->cacheSave($cacheItem->set($data)->expiresAfter($this->cacheTtl));
            }

            return $data;
        }

        $errorBody = is_array($data) ? $data : null;
        $detail = $errorBody['detail'] ?? null;
        $message = is_string($detail) ? $detail : sprintf('%s %s returned HTTP %d', $method, $uri, $status);

        throw match (true) {
            $status === 404 => new NotFoundException($message, $status, $errorBody),
            $status === 422 => new ValidationException($message, $status, $errorBody),
            $status === 429 => new RateLimitException($message, $status, $errorBody, null, self::retryAfter($response)),
            $status >= 500 => new ServerException($message, $status, $errorBody, null, self::retryAfter($response)),
            default => new BoaviztaException($message, $status, $errorBody),
        };
    }

    /**
     * PSR-17 implementations reject what they cannot represent with their own exception types
     * (e.g. Guzzle's MalformedUriException): translated, so that none reaches the caller.
     *
     * @throws ConfigurationException
     */
    private function buildRequest(string $method, string $uri, ?string $json): RequestInterface
    {
        try {
            $request = $this->requestFactory->createRequest($method, $uri)
                ->withHeader('Accept', 'application/json');
            if ($json !== null) {
                $request = $request
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody($this->streamFactory->createStream($json));
            }

            return $request;
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw new ConfigurationException(sprintf('Cannot build the request %s %s: %s', $method, $uri, $e->getMessage()), 0, null, $e);
        }
    }

    /**
     * The key carries the API version: after a server upgrade, responses cached in the old
     * format are no longer found, instead of being served as if they were current.
     */
    private function cacheKey(string $uri): string
    {
        return 'boavizta.' . sha1($this->apiVersion() . "\n" . $uri);
    }

    private function apiVersion(): string
    {
        if ($this->apiVersion === null) {
            $version = $this->send('GET', self::VERSION_PATH, []);
            if (!is_string($version) || $version === '') {
                throw UnexpectedResponseException::at('', 'should be the version string, got ' . get_debug_type($version));
            }
            $this->apiVersion = $version;
        }

        return $this->apiVersion;
    }

    /**
     * A failing cache only costs a request: it is logged, not thrown.
     */
    private function cacheItem(string $key): ?CacheItemInterface
    {
        try {
            return $this->cache?->getItem($key);
        } catch (CacheException|CacheInvalidArgumentException $e) {
            $this->logger?->warning('BoaviztAPI cache read failed: {error}', ['error' => $e->getMessage(), 'exception' => $e]);

            return null;
        }
    }

    private function cacheSave(CacheItemInterface $item): void
    {
        try {
            $this->cache?->save($item);
        } catch (CacheException|CacheInvalidArgumentException $e) {
            $this->logger?->warning('BoaviztAPI cache write failed: {error}', ['error' => $e->getMessage(), 'exception' => $e]);
        }
    }

    /**
     * Retry-After, in seconds: either a number of seconds or an HTTP date.
     */
    private static function retryAfter(ResponseInterface $response): ?int
    {
        $value = trim($response->getHeaderLine('Retry-After'));
        if ($value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }
        $date = \DateTimeImmutable::createFromFormat(DATE_RFC7231, $value, new \DateTimeZone('UTC'));

        return $date === false ? null : max(0, $date->getTimestamp() - time());
    }
}
