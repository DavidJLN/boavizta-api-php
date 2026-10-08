<?php

declare(strict_types=1);

namespace Boavizta\Api\Tests\Unit;

use Boavizta\Api\BoaviztaClient;
use Boavizta\Api\Exception\ConfigurationException;
use Boavizta\Api\Exception\TransportException;
use Boavizta\Api\Http\HttpClientFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

/**
 * The client built by the library gives up on a server that does not answer, whatever implementation
 * is installed. Proven against a local server that answers too late, not by reading configuration.
 */
final class TimeoutTest extends TestCase
{
    private const TIMEOUT = 0.5;

    /** @var resource|null */
    private static $server = null;
    private static string $baseUri = '';

    public static function setUpBeforeClass(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($socket);
        $address = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        // Several workers, so that a request still sleeping does not queue the next one.
        $command = [PHP_BINARY, '-S', $address, __DIR__ . '/Support/slow-server.php'];
        $process = proc_open($command, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, ['PHP_CLI_SERVER_WORKERS' => '8']);
        self::assertIsResource($process);
        self::$server = $process;
        self::$baseUri = "http://$address";

        $deadline = microtime(true) + 5;
        while (@fsockopen('127.0.0.1', (int) parse_url(self::$baseUri, PHP_URL_PORT)) === false) {
            self::assertLessThan($deadline, microtime(true), 'The local slow server did not start');
            usleep(50_000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
    }

    /**
     * @return iterable<string, array{\Closure(float): ?ClientInterface}>
     */
    public static function implementations(): iterable
    {
        yield 'symfony/http-client' => [HttpClientFactory::symfony(...)];
        yield 'guzzlehttp/guzzle' => [HttpClientFactory::guzzle(...)];
        yield 'php-http/curl-client' => [HttpClientFactory::curl(...)];
    }

    /**
     * @param \Closure(float): ?ClientInterface $build
     */
    #[DataProvider('implementations')]
    public function testEachKnownImplementationTimesOut(\Closure $build): void
    {
        $http = $build(self::TIMEOUT);
        self::assertNotNull($http, 'Installed in require-dev');

        $this->assertTimesOut(BoaviztaClient::create(self::$baseUri, $http));
    }

    public function testClientBuiltByDefaultTimesOut(): void
    {
        $this->assertTimesOut(BoaviztaClient::create(self::$baseUri, timeout: self::TIMEOUT));
    }

    public function testTimeoutIsOnByDefault(): void
    {
        $parameters = (new \ReflectionMethod(BoaviztaClient::class, 'create'))->getParameters();
        $timeout = array_values(array_filter($parameters, static fn (\ReflectionParameter $p): bool => $p->name === 'timeout'))[0] ?? null;

        self::assertNotNull($timeout);
        self::assertSame(BoaviztaClient::DEFAULT_TIMEOUT, $timeout->getDefaultValue());
        self::assertSame(30.0, BoaviztaClient::DEFAULT_TIMEOUT);
    }

    public function testNonPositiveTimeoutIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        BoaviztaClient::create(timeout: 0.0);
    }

    private function assertTimesOut(BoaviztaClient $client): void
    {
        $start = microtime(true);
        try {
            $client->utils()->version();
            self::fail('TransportException expected');
        } catch (TransportException $e) {
            self::assertTrue($e->isRetryable());
        }
        self::assertLessThan(3.0, microtime(true) - $start, 'Gave up long before the server answered (5 s)');
    }
}
