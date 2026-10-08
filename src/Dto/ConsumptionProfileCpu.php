<?php

declare(strict_types=1);

namespace Boavizta\Api\Dto;

final class ConsumptionProfileCpu extends AbstractDto
{
    /**
     * @param list<WorkloadPower>|null $workload
     */
    public function __construct(
        public readonly ?Cpu $cpu = null,
        public readonly ?array $workload = null,
    ) {
    }
}
