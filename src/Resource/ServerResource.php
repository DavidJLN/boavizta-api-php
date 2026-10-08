<?php

declare(strict_types=1);

namespace Boavizta\Api\Resource;

use Boavizta\Api\Dto\Server;
use Boavizta\Api\Request\ImpactOptions;
use Boavizta\Api\Response\ArchetypeConfig;
use Boavizta\Api\Response\ImpactResult;

/**
 * /v1/server
 */
final class ServerResource extends AbstractResource
{
    /**
     * @return list<string>
     */
    public function archetypes(): array
    {
        return $this->getStringList('/v1/server/archetypes');
    }

    public function archetypeConfig(string $archetype): ArchetypeConfig
    {
        return $this->getArchetypeConfig('/v1/server/archetype_config', ['archetype' => $archetype]);
    }

    /**
     * Impact of a server archetype (e.g. "dellR740"), or of the default server when null.
     */
    public function impactFromArchetype(?string $archetype = null, ?ImpactOptions $options = null): ImpactResult
    {
        return $this->getImpact('/v1/server/', $options, $archetype);
    }

    /**
     * Impact of a custom configuration; missing attributes are taken from `$archetype`.
     */
    public function impactFromConfiguration(Server $server, ?ImpactOptions $options = null, ?string $archetype = null): ImpactResult
    {
        return $this->postImpact('/v1/server/', $server, $options, $archetype);
    }
}
