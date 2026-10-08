<?php

declare(strict_types=1);

namespace Boavizta\Api\Dto;

use Boavizta\Api\Enum\DiskType;

/**
 * SSD or HDD. `capacity` is in GB, `density` in GB/cm².
 */
final class Disk extends AbstractDto
{
    public function __construct(
        public readonly ?int $units = null,
        public readonly ?DiskType $type = null,
        public readonly ?int $capacity = null,
        public readonly ?float $density = null,
        public readonly ?string $manufacturer = null,
        public readonly ?string $model = null,
        public readonly ?int $layers = null,
        public readonly ?Usage $usage = null,
    ) {
    }
}
