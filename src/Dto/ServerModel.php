<?php

declare(strict_types=1);

namespace Boavizta\Api\Dto;

final class ServerModel extends AbstractDto
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $archetype = null,
        public readonly ?string $type = null,
    ) {
    }
}
