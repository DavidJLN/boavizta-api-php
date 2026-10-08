<?php

declare(strict_types=1);

namespace Boavizta\Api\Dto;

/**
 * Power supply unit. `unitWeight` is in kg.
 */
final class PowerSupply extends AbstractDto
{
    public function __construct(
        public readonly ?int $units = null,
        public readonly ?float $unitWeight = null,
        public readonly ?Usage $usage = null,
    ) {
    }
}
