<?php

declare(strict_types=1);

namespace Boavizta\Api\Exception;

/**
 * HTTP 429: too many requests. `retryAfter()` gives the delay the server asked for, if any.
 */
final class RateLimitException extends BoaviztaException
{
    /**
     * @param array<mixed>|null $body
     */
    public function __construct(
        string $message,
        int $code = 429,
        ?array $body = null,
        ?\Throwable $previous = null,
        private readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $code, $body, $previous);
    }

    public function isRetryable(): bool
    {
        return true;
    }

    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
