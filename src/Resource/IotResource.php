<?php

declare(strict_types=1);

namespace Boavizta\Api\Resource;

use Boavizta\Api\Dto\IotDevice;
use Boavizta\Api\Request\ImpactOptions;
use Boavizta\Api\Response\ArchetypeConfig;
use Boavizta\Api\Response\ImpactResult;

/**
 * /v1/iot
 */
final class IotResource extends AbstractResource
{
    /**
     * @return list<string>
     */
    public function archetypes(): array
    {
        return $this->getStringList('/v1/iot/iot_device/archetypes');
    }

    public function archetypeConfig(string $archetype): ArchetypeConfig
    {
        return $this->getArchetypeConfig('/v1/iot/iot_device/archetype_config', ['archetype' => $archetype]);
    }

    public function impactFromArchetype(?string $archetype = null, ?ImpactOptions $options = null): ImpactResult
    {
        return $this->getImpact('/v1/iot/iot_device', $options, $archetype);
    }

    public function impact(IotDevice $device, ?ImpactOptions $options = null, ?string $archetype = null): ImpactResult
    {
        return $this->postImpact('/v1/iot/iot_device', $device, $options, $archetype);
    }
}
