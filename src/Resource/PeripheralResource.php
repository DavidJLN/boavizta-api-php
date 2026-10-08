<?php

declare(strict_types=1);

namespace Boavizta\Api\Resource;

use Boavizta\Api\Enum\PeripheralType;

/**
 * /v1/peripheral: monitors, USB sticks, external SSD/HDD, VR controllers.
 *
 * @extends AbstractUserDeviceResource<PeripheralType>
 */
final class PeripheralResource extends AbstractUserDeviceResource
{
    protected function prefix(): string
    {
        return '/v1/peripheral';
    }
}
