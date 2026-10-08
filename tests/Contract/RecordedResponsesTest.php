<?php

declare(strict_types=1);

namespace Boavizta\Api\Tests\Contract;

use Boavizta\Api\BoaviztaClient;
use Boavizta\Api\Tests\Contract\Support\Exchange;
use Boavizta\Api\Tests\Contract\Support\ReplayClient;
use Boavizta\Api\Tests\Contract\Support\Scenarios;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests: responses really sent by BoaviztAPI, recorded by bin/record-fixtures (date, URL and
 * API version in each recording's header), replayed offline.
 *
 * They prove the client reads what the API actually sent that day. They do not prove the API
 * still sends it today: that is the job of tests/Live, which replays the same scenarios
 * against the real server.
 *
 * A recording that no longer matches is regenerated with bin/record-fixtures, never edited by hand.
 */
final class RecordedResponsesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function scenarios(): iterable
    {
        foreach (array_keys(Scenarios::all()) as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('scenarios')]
    public function testClientReadsRecordedResponse(string $name): void
    {
        self::assertTrue(Exchange::exists($name), "No fixture for scenario $name: run composer record-fixtures -- $name");
        $exchange = Exchange::load($name);
        $factory = new HttpFactory();
        $client = BoaviztaClient::create('http://boavizta.test', new ReplayClient($exchange), $factory, $factory);

        $scenario = Scenarios::all()[$name];
        $scenario->check($scenario->run($client), $exchange->decodedResponse());
    }

    public function testEveryFixtureBelongsToAScenario(): void
    {
        $fixtures = array_map(static fn (string $file): string => basename($file, '.json'), glob(Exchange::FIXTURES_DIR . '/*.json') ?: []);

        self::assertSame([], array_values(array_diff($fixtures, array_keys(Scenarios::all()))), 'Recordings without a scenario: delete them');
    }

    /**
     * Exchange::load() refuses a recording whose header and body are not exactly what the recorder sealed.
     */
    #[DataProvider('scenarios')]
    public function testRecordingCarriesItsUrlDateAndApiVersion(string $name): void
    {
        $exchange = Exchange::load($name);

        self::assertTrue(str_ends_with((string) parse_url($exchange->url, PHP_URL_PATH), $exchange->path), "$exchange->url does not call $exchange->path");
        self::assertSame($exchange->query, (string) parse_url($exchange->url, PHP_URL_QUERY));
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', (string) $exchange->recordedAt);
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', (string) $exchange->apiVersion);
    }
}
