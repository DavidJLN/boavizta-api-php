<?php

declare(strict_types=1);

namespace Boavizta\Api\Dto;

/**
 * Body for every terminal and peripheral endpoint.
 * `type` is only meaningful for laptops, desktops, televisions and VR headsets.
 */
final class UserTerminal extends AbstractDto
{
    public function __construct(
        public readonly ?Usage $usage = null,
        public readonly ?string $type = null,
    ) {
    }
}
