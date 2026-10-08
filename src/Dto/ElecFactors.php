<?php

declare(strict_types=1);

namespace Boavizta\Api\Dto;

/**
 * Custom impact factors of the electricity mix, per kWh. Overrides `usage_location`.
 */
final class ElecFactors extends AbstractDto
{
    public function __construct(
        public readonly ?float $gwp = null,
        public readonly ?float $adp = null,
        public readonly ?float $pe = null,
        public readonly ?float $gwppb = null,
        public readonly ?float $gwppf = null,
        public readonly ?float $gwpplu = null,
        public readonly ?float $ir = null,
        public readonly ?float $lu = null,
        public readonly ?float $odp = null,
        public readonly ?float $pm = null,
        public readonly ?float $pocp = null,
        public readonly ?float $wu = null,
        public readonly ?float $mips = null,
        public readonly ?float $adpe = null,
        public readonly ?float $adpf = null,
        public readonly ?float $ap = null,
        public readonly ?float $ctue = null,
        public readonly ?float $ctuhC = null,
        public readonly ?float $ctuhNc = null,
        public readonly ?float $epf = null,
        public readonly ?float $epm = null,
        public readonly ?float $ept = null,
    ) {
    }
}
