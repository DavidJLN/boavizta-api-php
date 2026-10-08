<?php

declare(strict_types=1);

namespace Boavizta\Api\Exception;

/**
 * Base exception for every error raised by the client.
 *
 * Whatever PSR-18 client is used, only this hierarchy escapes the library; the client's own
 * exception, if any, is kept as `getPrevious()`.
 *
 * The client never retries. `isRetryable()` and `retryAfter()` give the application what it
 * needs to decide, and to wait, if it wants to.
 */
class BoaviztaException extends \RuntimeException
{
    /**
     * @param array<mixed>|null $body Decoded error body returned by the API, if any.
     */
    public function __construct(
        string $message,
        int $code = 0,
        public readonly ?array $body = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Whether sending the same request again may succeed.
     */
    public function isRetryable(): bool
    {
        return false;
    }

    /**
     * Seconds to wait before retrying, when the server said so (Retry-After header).
     */
    public function retryAfter(): ?int
    {
        return null;
    }
}
