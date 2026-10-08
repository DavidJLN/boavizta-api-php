<?php

declare(strict_types=1);

namespace Boavizta\Api\Resource;

use Boavizta\Api\Dto\CloudInstance;
use Boavizta\Api\Request\ImpactOptions;
use Boavizta\Api\Response\ArchetypeConfig;
use Boavizta\Api\Response\ImpactResult;

/**
 * /v1/cloud
 */
final class CloudResource extends AbstractResource
{
    /**
     * @return list<string>
     */
    public function providers(): array
    {
        return $this->getStringList('/v1/cloud/instance/all_providers');
    }

    /**
     * @return list<string>
     */
    public function instances(string $provider): array
    {
        return $this->getStringList('/v1/cloud/instance/all_instances', ['provider' => $provider]);
    }

    public function instanceConfig(string $provider, string $instanceType): ArchetypeConfig
    {
        return $this->getArchetypeConfig('/v1/cloud/instance/instance_config', [
            'provider' => $provider,
            'instance_type' => $instanceType,
        ]);
    }

    /**
     * Impact of an instance with the default usage hypotheses.
     */
    public function instanceImpact(string $provider, string $instanceType, ?ImpactOptions $options = null): ImpactResult
    {
        return $this->getImpact('/v1/cloud/instance', $options, null, [
            'provider' => $provider,
            'instance_type' => $instanceType,
        ]);
    }

    /**
     * Impact of an instance with custom usage (region, workload, lifetime...).
     */
    public function instanceImpactFromConfiguration(CloudInstance $instance, ?ImpactOptions $options = null): ImpactResult
    {
        return $this->postImpact('/v1/cloud/instance', $instance, $options);
    }
}
