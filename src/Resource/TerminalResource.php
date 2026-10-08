<?php

declare(strict_types=1);

namespace Boavizta\Api\Resource;

use Boavizta\Api\Enum\TerminalType;

/**
 * /v1/terminal: laptops, desktops, smartphones, tablets, televisions, boxes, VR headsets.
 *
 * @extends AbstractUserDeviceResource<TerminalType>
 */
final class TerminalResource extends AbstractUserDeviceResource
{
    protected function prefix(): string
    {
        return '/v1/terminal';
    }
}
