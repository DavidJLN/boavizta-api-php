<?php

declare(strict_types=1);

namespace Boavizta\Api\Resource;

use Boavizta\Api\Dto\ConsumptionProfileCpu;
use Boavizta\Api\Response\CpuConsumptionProfile;

/**
 * /v1/consumption_profile
 */
final class ConsumptionProfileResource extends AbstractResource
{
    /**
     * Fits a power consumption model (a, b, c, d coefficients) for a CPU,
     * optionally from measured workload points.
     */
    public function cpu(ConsumptionProfileCpu $profile): CpuConsumptionProfile
    {
        $path = '/v1/consumption_profile/cpu';

        return CpuConsumptionProfile::fromArray($this->expectArray($this->transport->post($path, $profile), $path));
    }
}
