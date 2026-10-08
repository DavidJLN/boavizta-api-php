<?php

declare(strict_types=1);

namespace Boavizta\Api\Dto;

class UsageServer extends Usage
{
    /**
     * @param float|list<WorkloadTime>|null $timeWorkload
     */
    public function __construct(
        ?float $useTimeRatio = null,
        ?float $hoursLifeTime = null,
        ?float $avgPower = null,
        float|array|null $timeWorkload = null,
        ?string $usageLocation = null,
        ?ElecFactors $elecFactors = null,
        public readonly ?float $otherConsumptionRatio = null,
    ) {
        parent::__construct($useTimeRatio, $hoursLifeTime, $avgPower, $timeWorkload, $usageLocation, $elecFactors);
    }
}
