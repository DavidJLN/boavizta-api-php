<?php

declare(strict_types=1);

namespace Boavizta\Api\Tests\Contract\Support;

use Boavizta\Api\BoaviztaClient;
use Boavizta\Api\Exception\BoaviztaException;

final class Scenario
{
    /**
     * @param \Closure(BoaviztaClient): mixed $call  Sends exactly one request through the public API of the client.
     * @param \Closure(mixed, mixed): void    $check Receives the result (or the BoaviztaException thrown) and the decoded response body.
     * @param list<string> $unstablePaths Dotted paths of response values the server does not reproduce from one call
     *                                    to the next: the live tests check their type, not their value. Each needs a reason.
     */
    public function __construct(
        public readonly string $name,
        private readonly \Closure $call,
        private readonly \Closure $check,
        public readonly array $unstablePaths = [],
    ) {
    }

    /**
     * Runs the call; an error answered by the API is returned rather than thrown, so it can be checked.
     */
    public function run(BoaviztaClient $client): mixed
    {
        try {
            return ($this->call)($client);
        } catch (BoaviztaException $e) {
            return $e;
        }
    }

    public function check(mixed $result, mixed $responseBody): void
    {
        ($this->check)($result, $responseBody);
    }
}
