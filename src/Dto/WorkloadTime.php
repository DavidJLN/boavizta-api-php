<?php

declare(strict_types=1);

namespace Boavizta\Api\Dto;

/**
 * Share of the device lifetime spent at a given load.
 */
final class WorkloadTime extends AbstractDto
{
    public function __construct(
        public readonly ?float $timePercentage = null,
        public readonly ?float $loadPercentage = null,
    ) {
    }
}
