<?php

declare(strict_types=1);

namespace Boavizta\Api\Dto;

/**
 * RAM module(s). `capacity` is in GB, `density` in GB/cm².
 */
final class Ram extends AbstractDto
{
    public function __construct(
        public readonly ?int $units = null,
        public readonly ?int $capacity = null,
        public readonly ?float $density = null,
        public readonly ?float $process = null,
        public readonly ?string $manufacturer = null,
        public readonly ?string $model = null,
        public readonly ?Usage $usage = null,
    ) {
    }
}
