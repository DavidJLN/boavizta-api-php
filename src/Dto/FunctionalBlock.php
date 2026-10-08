<?php

declare(strict_types=1);

namespace Boavizta\Api\Dto;

/**
 * Building block of an IoT device.
 */
final class FunctionalBlock extends AbstractDto
{
    public function __construct(
        public readonly ?int $units = null,
        public readonly ?string $hslLevel = null,
        public readonly ?string $type = null,
        public readonly ?Usage $usage = null,
    ) {
    }
}
