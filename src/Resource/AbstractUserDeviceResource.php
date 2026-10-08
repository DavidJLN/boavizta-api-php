<?php

declare(strict_types=1);

namespace Boavizta\Api\Resource;

use Boavizta\Api\Dto\UserTerminal;
use Boavizta\Api\Request\ImpactOptions;
use Boavizta\Api\Response\ArchetypeConfig;
use Boavizta\Api\Response\ImpactResult;

/**
 * Terminals and peripherals share the same endpoints: /{prefix}/{category}[/archetypes|/archetype_config].
 *
 * @template T of \BackedEnum
 */
abstract class AbstractUserDeviceResource extends AbstractResource
{
    abstract protected function prefix(): string;

    /**
     * Available categories mapped to their endpoint.
     *
     * @return array<string, string> Endpoint path keyed by category, e.g. "laptop" => "v1/terminal/laptop".
     */
    public function all(): array
    {
        return $this->getStringMap($this->prefix() . '/all');
    }

    /**
     * @param T $category
     *
     * @return list<string>
     */
    public function archetypes(\BackedEnum $category): array
    {
        return $this->getStringList($this->path($category) . '/archetypes');
    }

    /**
     * @param T $category
     */
    public function archetypeConfig(\BackedEnum $category, string $archetype): ArchetypeConfig
    {
        return $this->getArchetypeConfig($this->path($category) . '/archetype_config', ['archetype' => $archetype]);
    }

    /**
     * @param T $category
     */
    public function impactFromArchetype(\BackedEnum $category, ?string $archetype = null, ?ImpactOptions $options = null): ImpactResult
    {
        return $this->getImpact($this->path($category), $options, $archetype);
    }

    /**
     * @param T $category
     */
    public function impact(\BackedEnum $category, UserTerminal $device, ?ImpactOptions $options = null, ?string $archetype = null): ImpactResult
    {
        return $this->postImpact($this->path($category), $device, $options, $archetype);
    }

    /**
     * @param T $category
     */
    private function path(\BackedEnum $category): string
    {
        return $this->prefix() . '/' . $category->value;
    }
}
