<?php

declare(strict_types=1);

namespace Boavizta\Api\Response;

/**
 * One impact criterion the API can compute (utils()->impactCriteria()).
 */
final class ImpactCriterion
{
    public function __construct(
        public readonly string $name,
        public readonly string $unit,
        public readonly string $description,
        public readonly ?string $method,
    ) {
    }

    /**
     * Every key is required; `method` may be null (e.g. "PEF" for the Product Environmental Footprint criteria).
     *
     * @param array<mixed> $data
     *
     * @throws \Boavizta\Api\Exception\UnexpectedResponseException
     */
    public static function fromArray(array $data, string $path = ''): self
    {
        return new self(
            Payload::string(Payload::get($data, 'name', $path), Payload::join($path, 'name')),
            Payload::string(Payload::get($data, 'unit', $path), Payload::join($path, 'unit')),
            Payload::string(Payload::get($data, 'description', $path), Payload::join($path, 'description')),
            Payload::nullableString(Payload::get($data, 'method', $path), Payload::join($path, 'method')),
        );
    }
}
