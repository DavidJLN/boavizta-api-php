<?php

declare(strict_types=1);

namespace Boavizta\Api\Dto;

final class UsageCloud extends UsageServer
{
    /**
     * @param float|list<WorkloadTime>|null $timeWorkload
     * @param string|null $region Cloud region (see utils()->cloudRegions()), e.g. "eu-west-3".
     */
    public function __construct(
        ?float $useTimeRatio = null,
        ?float $hoursLifeTime = null,
        ?float $avgPower = null,
        float|array|null $timeWorkload = null,
        ?string $usageLocation = null,
        ?ElecFactors $elecFactors = null,
        ?float $otherConsumptionRatio = null,
        public readonly ?int $instancePerServer = null,
        public readonly ?string $region = null,
    ) {
        parent::__construct(
            $useTimeRatio,
            $hoursLifeTime,
            $avgPower,
            $timeWorkload,
            $usageLocation,
            $elecFactors,
            $otherConsumptionRatio,
        );
    }
}
