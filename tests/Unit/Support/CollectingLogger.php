<?php

declare(strict_types=1);

namespace Boavizta\Api\Tests\Unit\Support;

use Psr\Log\AbstractLogger;

/**
 * Minimal PSR-3 logger keeping records in memory, so the tests don't depend on Monolog.
 */
final class CollectingLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
    public array $records = [];

    /**
     * @param array<mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
