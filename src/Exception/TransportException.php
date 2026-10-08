<?php

declare(strict_types=1);

namespace Boavizta\Api\Exception;

/**
 * No usable HTTP response: network failure, timeout, or body cut while it was read.
 */
final class TransportException extends BoaviztaException
{
    public function isRetryable(): bool
    {
        return true;
    }
}
