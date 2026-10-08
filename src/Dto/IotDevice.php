<?php

declare(strict_types=1);

namespace Boavizta\Api\Dto;

final class IotDevice extends AbstractDto
{
    /**
     * @param list<FunctionalBlock>|null $functionalBlocks
     */
    public function __construct(
        public readonly ?array $functionalBlocks = null,
        public readonly ?Usage $usage = null,
    ) {
    }
}
