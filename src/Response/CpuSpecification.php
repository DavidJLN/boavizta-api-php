<?php

declare(strict_types=1);

namespace Boavizta\Api\Response;

use Boavizta\Api\Dto\Cpu;

/**
 * CPU resolved from a free-text name (utils()->nameToCpu()). Unknown attributes are null.
 */
final class CpuSpecification
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $manufacturer,
        public readonly ?string $modelRange,
        public readonly ?string $family,
        public readonly ?int $coreUnits,
        public readonly ?float $dieSize,
        public readonly ?float $dieSizePerCore,
        public readonly ?int $tdp,
    ) {
    }

    /**
     * Every key read is required, even when its value is null.
     *
     * @param array<mixed> $data
     *
     * @throws \Boavizta\Api\Exception\UnexpectedResponseException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Payload::nullableString(Payload::get($data, 'name', ''), 'name'),
            Payload::nullableString(Payload::get($data, 'manufacturer', ''), 'manufacturer'),
            Payload::nullableString(Payload::get($data, 'model_range', ''), 'model_range'),
            Payload::nullableString(Payload::get($data, 'family', ''), 'family'),
            Payload::nullableInt(Payload::get($data, 'core_units', ''), 'core_units'),
            Payload::nullableFloat(Payload::get($data, 'die_size', ''), 'die_size'),
            Payload::nullableFloat(Payload::get($data, 'die_size_per_core', ''), 'die_size_per_core'),
            Payload::nullableInt(Payload::get($data, 'tdp', ''), 'tdp'),
        );
    }

    /**
     * The same CPU as a request DTO, to compute its impact.
     */
    public function toCpu(?int $units = null): Cpu
    {
        return new Cpu(
            units: $units,
            coreUnits: $this->coreUnits,
            dieSize: $this->dieSize,
            dieSizePerCore: $this->dieSizePerCore,
            manufacturer: $this->manufacturer,
            modelRange: $this->modelRange,
            family: $this->family,
            name: $this->name,
            tdp: $this->tdp,
        );
    }
}
