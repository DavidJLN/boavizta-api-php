<?php

declare(strict_types=1);

namespace Boavizta\Api\Response;

/**
 * Configuration of an archetype (`archetypeConfig()`, `instanceConfig()`): the values the API
 * completes a request with.
 *
 * Attributes of the device itself are top-level parameters (e.g. "manufacturer", "vcpu"); those of
 * its parts are grouped under an upper-case name (e.g. "CPU" → "units", "USAGE" → "hours_life_time").
 */
final class ArchetypeConfig
{
    /**
     * @param array<string, ArchetypeParameter>                     $parameters
     * @param array<string, array<string, ArchetypeParameter>>      $groups
     */
    public function __construct(
        public readonly array $parameters,
        public readonly array $groups,
    ) {
    }

    /**
     * @param array<mixed> $data
     *
     * @throws \Boavizta\Api\Exception\UnexpectedResponseException
     */
    public static function fromArray(array $data): self
    {
        $parameters = [];
        $groups = [];
        foreach ($data as $key => $node) {
            $key = (string) $key;
            if ($key !== strtoupper($key)) {
                $parameters[$key] = ArchetypeParameter::fromArray($node, $key);
                continue;
            }
            $groups[$key] = [];
            foreach (Payload::object($node, $key) as $name => $parameter) {
                $groups[$key][(string) $name] = ArchetypeParameter::fromArray($parameter, "$key.$name");
            }
        }

        return new self($parameters, $groups);
    }

    /**
     * A parameter of the device, or of one of its groups; null when the archetype has none by that name.
     */
    public function parameter(string $name, ?string $group = null): ?ArchetypeParameter
    {
        return $group === null ? $this->parameters[$name] ?? null : $this->groups[$group][$name] ?? null;
    }

    /**
     * Shortcut for the default value of a parameter, null when it has none.
     */
    public function default(string $name, ?string $group = null): string|int|float|bool|null
    {
        return $this->parameter($name, $group)?->default;
    }
}
