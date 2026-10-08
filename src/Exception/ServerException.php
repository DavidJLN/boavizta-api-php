<?php

declare(strict_types=1);

namespace Boavizta\Api\Exception;

/**
 * HTTP 5xx.
 *
 * Only 502, 503 and 504 are retryable: they say the server could not answer. A 500 says it answered
 * and failed; BoaviztAPI returns one for an unknown criterion, which no retry will fix.
 */
final class ServerException extends BoaviztaException
{
    private const RETRYABLE_STATUSES = [502, 503, 504];

    /**
     * @param array<mixed>|null $body
     */
    public function __construct(
        string $message,
        int $code = 500,
        ?array $body = null,
        ?\Throwable $previous = null,
        private readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $code, $body, $previous);
    }

    public function isRetryable(): bool
    {
        return in_array($this->getCode(), self::RETRYABLE_STATUSES, true);
    }

    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
