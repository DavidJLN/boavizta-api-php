<?php

declare(strict_types=1);

namespace Boavizta\Api\Response;

use Boavizta\Api\Dto\Gpu;

/**
 * GPU resolved from a free-text name (utils()->nameToGpu()). Weights in kg, surfaces in cm², vram in GB, tdp in W.
 * Unknown attributes are null.
 */
final class GpuSpecification
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $manufacturer,
        public readonly ?float $weight,
        public readonly ?float $heatsinkWeight,
        public readonly ?float $pwbSurface,
        public readonly ?float $pwbWeight,
        public readonly ?float $casingWeight,
        public readonly ?float $gpuSurface,
        public readonly ?int $vram,
        public readonly ?int $vramDies,
        public readonly ?float $vramSurface,
        public readonly ?float $tdp,
        public readonly ?int $gpusPerCard,
        public readonly ?float $transportBoat,
        public readonly ?float $transportTruck,
        public readonly ?float $transportPlane,
    ) {
    }

    /**
     * Every key read is required, even when its value is null, except `tdp` and `gpus_per_card` (BoaviztAPI 2.6+).
     *
     * @param array<mixed> $data
     *
     * @throws \Boavizta\Api\Exception\UnexpectedResponseException
     */
    public static function fromArray(array $data): self
    {
        $string = static fn (string $key): ?string => Payload::nullableString(Payload::get($data, $key, ''), $key);
        $float = static fn (string $key): ?float => Payload::nullableFloat(Payload::get($data, $key, ''), $key);
        $int = static fn (string $key): ?int => Payload::nullableInt(Payload::get($data, $key, ''), $key);

        return new self(
            $string('name'),
            $string('manufacturer'),
            $float('weight'),
            $float('heatsink_weight'),
            $float('pwb_surface'),
            $float('pwb_weight'),
            $float('casing_weight'),
            $float('gpu_surface'),
            $int('vram'),
            $int('vram_dies'),
            $float('vram_surface'),
            // Sent since BoaviztAPI 2.6.0: optional, so that a 2.5 server keeps working.
            array_key_exists('tdp', $data) ? $float('tdp') : null,
            array_key_exists('gpus_per_card', $data) ? $int('gpus_per_card') : null,
            $float('transport_boat'),
            $float('transport_truck'),
            $float('transport_plane'),
        );
    }

    /**
     * The same GPU as a request DTO, to compute its impact.
     */
    public function toGpu(?int $units = null): Gpu
    {
        return new Gpu(
            units: $units,
            name: $this->name,
            manufacturer: $this->manufacturer,
            weight: $this->weight,
            heatsinkWeight: $this->heatsinkWeight,
            pwbSurface: $this->pwbSurface,
            pwbWeight: $this->pwbWeight,
            casingWeight: $this->casingWeight,
            gpuSurface: $this->gpuSurface,
            vram: $this->vram,
            vramDies: $this->vramDies,
            vramSurface: $this->vramSurface,
            tdp: $this->tdp,
            gpusPerCard: $this->gpusPerCard,
            transportBoat: $this->transportBoat,
            transportTruck: $this->transportTruck,
            transportPlane: $this->transportPlane,
        );
    }
}
