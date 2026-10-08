<?php

declare(strict_types=1);

namespace Boavizta\Api\Response;

use Boavizta\Api\Exception\UnexpectedResponseException;

/**
 * One attribute of an archetype: the value the API uses when the request leaves it out, and its range.
 * Each of the three is omitted by the API when the archetype does not define it.
 */
final class ArchetypeParameter
{
    private const KEYS = ['default', 'min', 'max'];

    public function __construct(
        public readonly bool $hasDefault,
        public readonly string|int|float|bool|null $default,
        public readonly ?float $min,
        public readonly ?float $max,
    ) {
    }

    /**
     * Whether a node of an archetype configuration is a parameter (only `default`, `min`, `max` keys).
     */
    public static function matches(mixed $data): bool
    {
        return is_array($data) && array_diff(array_map('strval', array_keys($data)), self::KEYS) === [];
    }

    /**
     * @throws UnexpectedResponseException
     */
    public static function fromArray(mixed $data, string $path): self
    {
        if (!self::matches($data)) {
            throw UnexpectedResponseException::at($path, 'should be an object with only "default", "min" or "max"');
        }
        /** @var array<string, mixed> $data */
        $default = $data['default'] ?? null;
        if (!is_scalar($default) && $default !== null) {
            throw UnexpectedResponseException::at(Payload::join($path, 'default'), 'should be a scalar, got ' . get_debug_type($default));
        }

        return new self(
            array_key_exists('default', $data),
            $default,
            array_key_exists('min', $data) ? Payload::float($data['min'], Payload::join($path, 'min')) : null,
            array_key_exists('max', $data) ? Payload::float($data['max'], Payload::join($path, 'max')) : null,
        );
    }
}
