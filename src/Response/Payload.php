<?php

declare(strict_types=1);

namespace Boavizta\Api\Response;

use Boavizta\Api\Exception\UnexpectedResponseException;

/**
 * Typed reads from a decoded response. A missing key or a wrong type throws, nothing defaults to null.
 *
 * Every method takes the dotted path of the value, so the exception says where the response broke.
 *
 * @internal
 */
final class Payload
{
    /**
     * @return array<mixed>
     */
    public static function object(mixed $value, string $path): array
    {
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw UnexpectedResponseException::at($path, 'should be an object, got ' . self::describe($value));
        }

        return $value;
    }

    /**
     * @return list<mixed>
     */
    public static function list(mixed $value, string $path): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw UnexpectedResponseException::at($path, 'should be a list, got ' . self::describe($value));
        }

        return $value;
    }

    public static function string(mixed $value, string $path): string
    {
        if (!is_string($value)) {
            throw UnexpectedResponseException::at($path, 'should be a string, got ' . self::describe($value));
        }

        return $value;
    }

    public static function float(mixed $value, string $path): float
    {
        if (!is_int($value) && !is_float($value)) {
            throw UnexpectedResponseException::at($path, 'should be a number, got ' . self::describe($value));
        }

        return (float) $value;
    }

    public static function int(mixed $value, string $path): int
    {
        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }
        if (!is_int($value)) {
            throw UnexpectedResponseException::at($path, 'should be an integer, got ' . self::describe($value));
        }

        return $value;
    }

    public static function nullableString(mixed $value, string $path): ?string
    {
        return $value === null ? null : self::string($value, $path);
    }

    public static function nullableFloat(mixed $value, string $path): ?float
    {
        return $value === null ? null : self::float($value, $path);
    }

    public static function nullableInt(mixed $value, string $path): ?int
    {
        return $value === null ? null : self::int($value, $path);
    }

    /**
     * @return list<string>
     */
    public static function stringList(mixed $value, string $path): array
    {
        $list = [];
        foreach (self::list($value, $path) as $i => $item) {
            $list[] = self::string($item, self::join($path, $i));
        }

        return $list;
    }

    /**
     * @return array<string, string>
     */
    public static function stringMap(mixed $value, string $path): array
    {
        $map = [];
        foreach (self::object($value, $path) as $key => $item) {
            $map[(string) $key] = self::string($item, self::join($path, (string) $key));
        }

        return $map;
    }

    /**
     * The value at `$key`, which must be present (even if null).
     *
     * @param array<mixed> $object
     */
    public static function get(array $object, string $key, string $path): mixed
    {
        if (!array_key_exists($key, $object)) {
            throw UnexpectedResponseException::at(self::join($path, $key), 'is missing');
        }

        return $object[$key];
    }

    public static function join(string $path, string|int $key): string
    {
        return $path === '' ? (string) $key : "$path.$key";
    }

    private static function describe(mixed $value): string
    {
        return match (true) {
            is_array($value) => $value === [] ? 'an empty array' : (array_is_list($value) ? 'a list' : 'an object'),
            is_string($value) => sprintf('"%s"', strlen($value) > 40 ? substr($value, 0, 40) . '…' : $value),
            default => get_debug_type($value),
        };
    }
}
