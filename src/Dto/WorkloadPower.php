<?php

declare(strict_types=1);

namespace Boavizta\Api\Dto;

/**
 * A measured point of a consumption profile.
 */
final class WorkloadPower extends AbstractDto
{
    public function __construct(
        public readonly ?float $loadPercentage = null,
        public readonly ?float $powerWatt = null,
    ) {
    }
}
