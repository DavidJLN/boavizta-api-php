<?php

declare(strict_types=1);

namespace Boavizta\Api\Dto;

/**
 * A cloud instance, e.g. provider "aws" and instance type "a1.4xlarge".
 * Unknown instance types are fuzzy-matched by the API (see ImpactResult::$warnings).
 */
final class CloudInstance extends AbstractDto
{
    public function __construct(
        public readonly ?string $provider = null,
        public readonly ?string $instanceType = null,
        public readonly ?UsageCloud $usage = null,
    ) {
    }
}
