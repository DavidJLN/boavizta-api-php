<?php

declare(strict_types=1);

namespace Boavizta\Api\Dto;

/**
 * Server case (`case` is a reserved word in PHP). `caseType` is "rack" or "blade".
 */
final class CaseComponent extends AbstractDto
{
    public function __construct(
        public readonly ?int $units = null,
        public readonly ?string $caseType = null,
        public readonly ?Usage $usage = null,
    ) {
    }
}
