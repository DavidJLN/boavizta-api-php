<?php

declare(strict_types=1);

namespace Boavizta\Api\Resource;

use Boavizta\Api\Dto\AbstractDto;
use Boavizta\Api\Exception\UnexpectedResponseException;
use Boavizta\Api\Http\Transport;
use Boavizta\Api\Request\ImpactOptions;
use Boavizta\Api\Response\ArchetypeConfig;
use Boavizta\Api\Response\ImpactResult;
use Boavizta\Api\Response\Payload;

abstract class AbstractResource
{
    public function __construct(protected readonly Transport $transport)
    {
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $extraQuery
     */
    protected function getImpact(string $path, ?ImpactOptions $options, ?string $archetype = null, array $extraQuery = []): ImpactResult
    {
        $query = ($options ?? new ImpactOptions())->toQuery($archetype) + $extraQuery;

        return ImpactResult::fromArray($this->expectArray($this->transport->get($path, $query), $path));
    }

    protected function postImpact(string $path, ?AbstractDto $body, ?ImpactOptions $options, ?string $archetype = null): ImpactResult
    {
        $query = ($options ?? new ImpactOptions())->toQuery($archetype);

        return ImpactResult::fromArray($this->expectArray($this->transport->post($path, $body, $query), $path));
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $query
     *
     * @return array<mixed>
     */
    protected function getArray(string $path, array $query = []): array
    {
        return $this->expectArray($this->transport->get($path, $query), $path);
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $query
     *
     * @return list<string>
     */
    protected function getStringList(string $path, array $query = []): array
    {
        return Payload::stringList($this->transport->get($path, $query), '');
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $query
     *
     * @return array<string, string>
     */
    protected function getStringMap(string $path, array $query = []): array
    {
        return Payload::stringMap($this->transport->get($path, $query), '');
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $query
     */
    protected function getArchetypeConfig(string $path, array $query): ArchetypeConfig
    {
        return ArchetypeConfig::fromArray($this->getArray($path, $query));
    }

    /**
     * @return array<mixed>
     *
     * @throws UnexpectedResponseException
     */
    protected function expectArray(mixed $data, string $path): array
    {
        if (!is_array($data)) {
            throw new UnexpectedResponseException(sprintf('Unexpected response from %s: JSON array or object expected, got %s', $path, get_debug_type($data)));
        }

        return $data;
    }
}
