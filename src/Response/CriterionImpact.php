<?php

declare(strict_types=1);

namespace Boavizta\Api\Response;

use Boavizta\Api\Exception\UnexpectedResponseException;

/**
 * Impact for one criterion. A phase is null when the API answers "not implemented".
 */
final class CriterionImpact
{
    public const NOT_IMPLEMENTED = 'not implemented';

    public function __construct(
        public readonly string $criterion,
        public readonly string $unit,
        public readonly string $description,
        public readonly ?PhaseImpact $embedded,
        public readonly ?PhaseImpact $use,
    ) {
    }

    /**
     * Every key is required. A phase is either an object or the string "not implemented".
     *
     * @param array<mixed> $data
     * @param string       $path Dotted path of this criterion in the response, for error messages.
     *
     * @throws UnexpectedResponseException
     */
    public static function fromArray(string $criterion, array $data, string $path = ''): self
    {
        return new self(
            $criterion,
            Payload::string(Payload::get($data, 'unit', $path), Payload::join($path, 'unit')),
            Payload::string(Payload::get($data, 'description', $path), Payload::join($path, 'description')),
            self::phase(Payload::get($data, 'embedded', $path), Payload::join($path, 'embedded')),
            self::phase(Payload::get($data, 'use', $path), Payload::join($path, 'use')),
        );
    }

    /**
     * Sum of embedded and use values, ignoring unimplemented phases.
     */
    public function total(): float
    {
        return ($this->embedded->value ?? 0.0) + ($this->use->value ?? 0.0);
    }

    private static function phase(mixed $data, string $path): ?PhaseImpact
    {
        if ($data === self::NOT_IMPLEMENTED) {
            return null;
        }
        if (!is_array($data)) {
            throw UnexpectedResponseException::at($path, sprintf('should be an object or "%s", got %s', self::NOT_IMPLEMENTED, get_debug_type($data)));
        }

        return PhaseImpact::fromArray(Payload::object($data, $path), $path);
    }
}
