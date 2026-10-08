<?php

declare(strict_types=1);

namespace Boavizta\Api\Dto;

/**
 * Usage hypotheses shared by every device and component.
 */
class Usage extends AbstractDto
{
    /**
     * @param float|list<WorkloadTime>|null $timeWorkload A constant load percentage, or a load profile.
     * @param string|null $usageLocation ISO 3166-1 alpha-3 country code (see utils()->countryCodes()), e.g. "FRA".
     */
    public function __construct(
        public readonly ?float $useTimeRatio = null,
        public readonly ?float $hoursLifeTime = null,
        public readonly ?float $avgPower = null,
        public readonly float|array|null $timeWorkload = null,
        public readonly ?string $usageLocation = null,
        public readonly ?ElecFactors $elecFactors = null,
    ) {
    }
}
