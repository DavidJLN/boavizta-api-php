<?php

declare(strict_types=1);

namespace Boavizta\Api\Dto;

final class ServerConfiguration extends AbstractDto
{
    /**
     * @param list<Ram>|null  $ram
     * @param list<Disk>|null $disk
     */
    public function __construct(
        public readonly ?Cpu $cpu = null,
        public readonly ?array $ram = null,
        public readonly ?array $disk = null,
        public readonly ?Gpu $gpu = null,
        public readonly ?PowerSupply $powerSupply = null,
    ) {
    }
}
