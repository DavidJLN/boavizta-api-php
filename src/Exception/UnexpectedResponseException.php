<?php

declare(strict_types=1);

namespace Boavizta\Api\Exception;

/**
 * A successful response that does not match the API contract: invalid JSON, missing key, wrong type.
 *
 * Usually means the server was upgraded to a format this client does not know.
 */
final class UnexpectedResponseException extends BoaviztaException
{
    /**
     * @param string $field Dotted path of the offending value in the response (empty for the whole body).
     */
    public static function at(string $field, string $problem): self
    {
        return new self(sprintf('Unexpected BoaviztAPI response: %s %s', $field === '' ? 'the body' : '"' . $field . '"', $problem));
    }
}
