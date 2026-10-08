<?php

declare(strict_types=1);

namespace Boavizta\Api\Response;

/**
 * One region of a cloud provider (utils()->cloudRegions()).
 */
final class CloudRegion
{
    public function __construct(
        public readonly string $provider,
        public readonly string $region,
    ) {
    }

    /**
     * @param array<mixed> $data
     *
     * @throws \Boavizta\Api\Exception\UnexpectedResponseException
     */
    public static function fromArray(array $data, string $path = ''): self
    {
        return new self(
            Payload::string(Payload::get($data, 'provider', $path), Payload::join($path, 'provider')),
            Payload::string(Payload::get($data, 'region', $path), Payload::join($path, 'region')),
        );
    }
}
