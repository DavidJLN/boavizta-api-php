<?php

declare(strict_types=1);

namespace Boavizta\Api\Dto;

final class Motherboard extends AbstractDto
{
    public function __construct(
        public readonly ?int $units = null,
        public readonly ?Usage $usage = null,
    ) {
    }
}
