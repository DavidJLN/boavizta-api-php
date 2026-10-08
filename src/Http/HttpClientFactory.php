<?php

declare(strict_types=1);

namespace Boavizta\Api\Http;

use Boavizta\Api\Exception\ConfigurationException;
use Http\Discovery\Exception\NotFoundException as DiscoveryNotFoundException;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;

/**
 * Builds the PSR-18 client when the application does not give one, with a timeout.
 *
 * PSR-18 has no timeout option, so it can only be set on a client this library builds itself,
 * from one of the implementations it knows. A client given by the application keeps its own settings.
 *
 * @internal
 */
final class HttpClientFactory
{
    /**
     * @param float|null $timeout Seconds; null builds the discovered client as is, without any timeout.
     *
     * @throws ConfigurationException When no client is installed, or none whose timeout can be set.
     */
    public static function create(?float $timeout): ClientInterface
    {
        if ($timeout !== null && $timeout <= 0) {
            throw new ConfigurationException(sprintf('The timeout must be positive, %s given; pass null to disable it', $timeout));
        }

        if ($timeout !== null) {
            $client = self::withTimeout($timeout);
            if ($client !== null) {
                return $client;
            }
        }

        try {
            $client = Psr18ClientDiscovery::find();
        } catch (DiscoveryNotFoundException $e) {
            throw new ConfigurationException('No PSR-18 HTTP client found: install one (e.g. composer require symfony/http-client nyholm/psr7) or pass yours to BoaviztaClient::create()', 0, null, $e);
        }

        if ($timeout !== null) {
            throw new ConfigurationException(sprintf(
                'Cannot set a timeout on the discovered %s: pass your own client, configured with a timeout, to BoaviztaClient::create(), or pass timeout: null to go without one',
                $client::class,
            ));
        }

        return $client;
    }

    private static function withTimeout(float $timeout): ?ClientInterface
    {
        return self::symfony($timeout) ?? self::guzzle($timeout) ?? self::curl($timeout);
    }

    /**
     * Null when symfony/http-client is not installed.
     */
    public static function symfony(float $timeout): ?ClientInterface
    {
        if (!class_exists(\Symfony\Component\HttpClient\Psr18Client::class)) {
            return null;
        }

        // `timeout` bounds the wait between two bytes, `max_duration` the whole exchange.
        return new \Symfony\Component\HttpClient\Psr18Client(
            \Symfony\Component\HttpClient\HttpClient::create(['timeout' => $timeout, 'max_duration' => $timeout]),
        );
    }

    /**
     * Null when guzzlehttp/guzzle 7+ (the first PSR-18 version) is not installed.
     */
    public static function guzzle(float $timeout): ?ClientInterface
    {
        if (!class_exists(\GuzzleHttp\Client::class) || !defined(\GuzzleHttp\ClientInterface::class . '::MAJOR_VERSION')) {
            return null;
        }

        return new \GuzzleHttp\Client(['timeout' => $timeout, 'connect_timeout' => $timeout]);
    }

    /**
     * Null when php-http/curl-client is not installed.
     */
    public static function curl(float $timeout): ?ClientInterface
    {
        if (!class_exists(\Http\Client\Curl\Client::class)) {
            return null;
        }
        $milliseconds = (int) ceil($timeout * 1000);

        return new \Http\Client\Curl\Client(null, null, [CURLOPT_TIMEOUT_MS => $milliseconds, CURLOPT_CONNECTTIMEOUT_MS => $milliseconds]);
    }
}
