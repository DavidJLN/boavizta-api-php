<?php

declare(strict_types=1);

namespace Boavizta\Api\Dto;

/**
 * CPU description. `name` alone (e.g. "Intel Xeon Gold 6134") is often enough: the API fuzzy-matches it.
 */
final class Cpu extends AbstractDto
{
    public function __construct(
        public readonly ?int $units = null,
        public readonly ?int $coreUnits = null,
        public readonly ?float $dieSize = null,
        public readonly ?float $dieSizePerCore = null,
        public readonly ?string $manufacturer = null,
        public readonly ?string $modelRange = null,
        public readonly ?string $family = null,
        public readonly ?string $name = null,
        public readonly ?int $tdp = null,
        public readonly ?Usage $usage = null,
    ) {
    }
}
