<?php

declare(strict_types=1);

namespace Boavizta\Api\Enum;

enum DiskType: string
{
    case Ssd = 'ssd';
    case Hdd = 'hdd';
}
