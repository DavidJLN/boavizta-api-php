<?php

declare(strict_types=1);

namespace Boavizta\Api\Exception;

/**
 * HTTP 422: the request was rejected by FastAPI validation.
 */
final class ValidationException extends BoaviztaException
{
    /**
     * @return list<array{loc?: list<string|int>, msg?: string, type?: string}>
     */
    public function errors(): array
    {
        $detail = $this->body['detail'] ?? [];

        return is_array($detail) ? array_values($detail) : [];
    }
}
