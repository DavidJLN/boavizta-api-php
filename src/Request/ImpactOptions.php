<?php

declare(strict_types=1);

namespace Boavizta\Api\Request;

use Boavizta\Api\Enum\Criterion;

/**
 * Query parameters shared by every impact endpoint.
 */
final class ImpactOptions
{
    /**
     * @param list<Criterion|string>|null $criteria Defaults server-side to gwp, adp and pe.
     * @param float|null $duration Duration of the assessment in hours. Defaults to the device lifetime.
     * @param bool $verbose Return the details of every attribute used (and how it was completed).
     */
    public function __construct(
        public readonly ?array $criteria = null,
        public readonly ?float $duration = null,
        public readonly bool $verbose = false,
    ) {
    }

    /**
     * @return array<string, scalar|list<scalar>|null>
     */
    public function toQuery(?string $archetype = null): array
    {
        return [
            'verbose' => $this->verbose,
            'duration' => $this->duration,
            'archetype' => $archetype,
            'criteria' => $this->criteria === null
                ? null
                : array_map(static fn (Criterion|string $c): string => $c instanceof Criterion ? $c->value : $c, $this->criteria),
        ];
    }
}
