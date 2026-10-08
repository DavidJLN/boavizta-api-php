<?php

declare(strict_types=1);

namespace Boavizta\Api\Dto;

/**
 * Serializes public promoted properties to the snake_case payload expected by the API.
 * Null properties are omitted so that BoaviztAPI completes them from the archetype.
 */
abstract class AbstractDto implements \JsonSerializable
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];
        foreach (get_object_vars($this) as $name => $value) {
            if ($value === null) {
                continue;
            }
            $data[self::toSnakeCase($name)] = self::normalize($value);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>|\stdClass
     */
    public function jsonSerialize(): array|\stdClass
    {
        $data = $this->toArray();

        // An empty DTO must be sent as `{}`, not `[]`.
        return $data === [] ? new \stdClass() : $data;
    }

    private static function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof self => $value->jsonSerialize(),
            $value instanceof \BackedEnum => $value->value,
            is_array($value) => array_map(self::normalize(...), $value),
            default => $value,
        };
    }

    private static function toSnakeCase(string $name): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }
}
