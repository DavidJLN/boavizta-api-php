<?php

declare(strict_types=1);

namespace Boavizta\Api\Dto;

/**
 * GPU description. `name` alone (e.g. "NVIDIA A100 80GB SXM") is often enough. Weights in kg, surfaces in cm², vram in GB.
 */
final class Gpu extends AbstractDto
{
    public function __construct(
        public readonly ?int $units = null,
        public readonly ?string $name = null,
        public readonly ?string $manufacturer = null,
        public readonly ?float $weight = null,
        public readonly ?float $heatsinkWeight = null,
        public readonly ?float $pwbSurface = null,
        public readonly ?float $pwbWeight = null,
        public readonly ?float $casingWeight = null,
        public readonly ?float $gpuSurface = null,
        public readonly ?int $vram = null,
        public readonly ?int $vramDies = null,
        public readonly ?float $vramSurface = null,
        public readonly ?float $transportBoat = null,
        public readonly ?float $transportTruck = null,
        public readonly ?float $transportPlane = null,
        public readonly ?Usage $usage = null,
    ) {
    }
}
