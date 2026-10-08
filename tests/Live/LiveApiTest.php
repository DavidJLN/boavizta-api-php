<?php

declare(strict_types=1);

namespace Boavizta\Api\Tests\Live;

use Boavizta\Api\BoaviztaClient;
use Boavizta\Api\Tests\Contract\Support\Exchange;
use Boavizta\Api\Tests\Contract\Support\RecordingClient;
use Boavizta\Api\Tests\Contract\Support\Scenarios;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Live tests: the contract scenarios, sent to a real BoaviztAPI and compared with the recording.
 *
 * They prove the recorded fixtures still match reality. They are excluded from the default run
 * (network, third-party server) and run on a schedule instead (.github/workflows/live.yml):
 *
 *     composer test:live                                          # api.boavizta.org
 *     BOAVIZTA_API_URL=http://localhost:5000 composer test:live   # docker run -p 5000:5000 ghcr.io/boavizta/boaviztapi:latest
 *
 * Values are compared byte for byte, except at the few paths a scenario declares unstable
 * (values the server recomputes differently at each call), which are compared by type only.
 *
 * When one fails, the API changed: regenerate the recording (`bin/record-fixtures`), read its diff,
 * and fix the client if the contract tests now fail.
 */
#[Group('live')]
final class LiveApiTest extends TestCase
{
    private const RERECORD = ' Regenerate with `bin/record-fixtures %s` and read the diff; never edit the recording by hand.';

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
    public function testRecordingStillMatchesTheRealApi(string $name): void
    {
        $baseUri = rtrim(getenv('BOAVIZTA_API_URL') ?: BoaviztaClient::DEFAULT_BASE_URI, '/');
        $streamFactory = Psr17FactoryDiscovery::findStreamFactory();
        $recorder = new RecordingClient(Psr18ClientDiscovery::find(), $streamFactory, rtrim((string) parse_url($baseUri, PHP_URL_PATH), '/'));
        $client = BoaviztaClient::create($baseUri, $recorder, Psr17FactoryDiscovery::findRequestFactory(), $streamFactory);

        $scenario = Scenarios::all()[$name];
        $result = $scenario->run($client);
        self::assertCount(1, $recorder->exchanges, 'A scenario sends exactly one request');
        $live = $recorder->lastExchange();
        $recorded = Exchange::load($name);

        // The client still behaves as described by today's API.
        $scenario->check($result, $live->decodedResponse());

        // The recording still describes today's API, from the coarsest difference to the finest.
        self::assertSame(
            [$recorded->method, $recorded->path, $recorded->query, $recorded->requestBody],
            [$live->method, $live->path, $live->query, $live->requestBody],
            'The recorded request is not the one the client sends.' . sprintf(self::RERECORD, $name),
        );
        self::assertSame($recorded->status, $live->status, 'HTTP status changed.' . sprintf(self::RERECORD, $name));
        self::assertSame(
            self::shape($recorded->decodedResponse()),
            self::shape($live->decodedResponse()),
            'Response structure changed (fields added, removed or retyped).' . sprintf(self::RERECORD, $name),
        );
        if ($scenario->unstablePaths === []) {
            self::assertSame($recorded->responseBody, $live->responseBody, 'Same structure, different values.' . sprintf(self::RERECORD, $name));
        } else {
            self::assertSame(
                self::withoutUnstableValues($recorded->decodedResponse(), $scenario->unstablePaths),
                self::withoutUnstableValues($live->decodedResponse(), $scenario->unstablePaths),
                'Same structure, different values (outside the paths declared unstable).' . sprintf(self::RERECORD, $name),
            );
        }
    }

    /**
     * Replaces the value at each dotted path by its shape, which the structure check already compared.
     *
     * @param list<string> $paths
     */
    private static function withoutUnstableValues(mixed $body, array $paths): mixed
    {
        foreach ($paths as $path) {
            $node = &$body;
            foreach (explode('.', $path) as $key) {
                self::assertIsArray($node, "Unstable path $path does not exist in the response");
                self::assertArrayHasKey($key, $node, "Unstable path $path does not exist in the response");
                $node = &$node[$key];
            }
            $node = self::shape($node);
            unset($node);
        }

        return $body;
    }

    /**
     * JSON value reduced to its types: objects keep their keys, lists the union of their items' shapes.
     */
    private static function shape(mixed $value): mixed
    {
        if (!is_array($value)) {
            return match (true) {
                $value === null => 'null',
                is_bool($value) => 'bool',
                is_int($value), is_float($value) => 'number',
                default => get_debug_type($value),
            };
        }
        if (!array_is_list($value)) {
            $shape = array_map(self::shape(...), $value);
            ksort($shape);

            return $shape;
        }

        $items = [];
        foreach ($value as $item) {
            $shape = self::shape($item);
            $items[json_encode($shape, JSON_THROW_ON_ERROR)] = $shape;
        }
        ksort($items);

        return ['list of' => array_values($items)];
    }
}
