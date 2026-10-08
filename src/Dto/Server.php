<?php

declare(strict_types=1);

namespace Boavizta\Api\Dto;

final class Server extends AbstractDto
{
    public function __construct(
        public readonly ?ServerModel $model = null,
        public readonly ?ServerConfiguration $configuration = null,
        public readonly ?UsageServer $usage = null,
    ) {
    }
}
