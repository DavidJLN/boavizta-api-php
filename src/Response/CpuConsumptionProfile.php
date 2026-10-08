<?php

declare(strict_types=1);

namespace Boavizta\Api\Response;

/**
 * Fitted CPU power model: power(load) = a × ln(b × (load + c)) + d, load in %, power in W.
 */
final class CpuConsumptionProfile
{
    public function __construct(
        public readonly float $a,
        public readonly float $b,
        public readonly float $c,
        public readonly float $d,
    ) {
    }

    /**
     * @param array<mixed> $data
     *
     * @throws \Boavizta\Api\Exception\UnexpectedResponseException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Payload::float(Payload::get($data, 'a', ''), 'a'),
            Payload::float(Payload::get($data, 'b', ''), 'b'),
            Payload::float(Payload::get($data, 'c', ''), 'c'),
            Payload::float(Payload::get($data, 'd', ''), 'd'),
        );
    }

    /**
     * Power drawn, in watts, at a load between 0 and 100 %.
     */
    public function power(float $load): float
    {
        return $this->a * log($this->b * ($load + $this->c)) + $this->d;
    }
}
