<?php

declare(strict_types=1);

namespace Boavizta\Api\Resource;

use Boavizta\Api\Dto\CaseComponent;
use Boavizta\Api\Dto\Cpu;
use Boavizta\Api\Dto\Disk;
use Boavizta\Api\Dto\Gpu;
use Boavizta\Api\Dto\Motherboard;
use Boavizta\Api\Dto\PowerSupply;
use Boavizta\Api\Dto\Ram;
use Boavizta\Api\Enum\ComponentType;
use Boavizta\Api\Request\ImpactOptions;
use Boavizta\Api\Response\ArchetypeConfig;
use Boavizta\Api\Response\ImpactResult;

/**
 * /v1/component
 */
final class ComponentResource extends AbstractResource
{
    /**
     * Available component categories mapped to their endpoint.
     *
     * @return array<string, string> Endpoint path keyed by category, e.g. "cpu" => "v1/component/cpu".
     */
    public function all(): array
    {
        return $this->getStringMap('/v1/component/all');
    }

    /**
     * @return list<string>
     */
    public function archetypes(ComponentType $type): array
    {
        return $this->getStringList('/v1/component/' . $type->archetypesPath());
    }

    public function archetypeConfig(ComponentType $type, string $archetype): ArchetypeConfig
    {
        return $this->getArchetypeConfig('/v1/component/' . $type->value . '/archetype_config', ['archetype' => $archetype]);
    }

    public function impactFromArchetype(ComponentType $type, ?string $archetype = null, ?ImpactOptions $options = null): ImpactResult
    {
        return $this->getImpact('/v1/component/' . $type->value, $options, $archetype);
    }

    /**
     * The component type is inferred from the DTO class; a Disk needs its `type` set (ssd or hdd).
     */
    public function impact(
        Cpu|Gpu|Ram|Disk|PowerSupply|Motherboard|CaseComponent $component,
        ?ImpactOptions $options = null,
        ?string $archetype = null,
    ): ImpactResult {
        $type = match (true) {
            $component instanceof Cpu => ComponentType::Cpu,
            $component instanceof Gpu => ComponentType::Gpu,
            $component instanceof Ram => ComponentType::Ram,
            $component instanceof Disk => ComponentType::from(($component->type ?? throw new \InvalidArgumentException('Disk::$type is required to pick the ssd or hdd endpoint'))->value),
            $component instanceof PowerSupply => ComponentType::PowerSupply,
            $component instanceof Motherboard => ComponentType::Motherboard,
            $component instanceof CaseComponent => ComponentType::Case,
        };

        return $this->postImpact('/v1/component/' . $type->value, $component, $options, $archetype);
    }
}
