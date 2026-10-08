<?php

declare(strict_types=1);

namespace Boavizta\Api\Resource;

use Boavizta\Api\Http\Transport;
use Boavizta\Api\Response\CloudRegion;
use Boavizta\Api\Response\CpuSpecification;
use Boavizta\Api\Response\GpuSpecification;
use Boavizta\Api\Response\ImpactCriterion;
use Boavizta\Api\Response\Payload;

/**
 * /v1/utils: reference data.
 */
final class UtilsResource extends AbstractResource
{
    public function version(): string
    {
        return Payload::string($this->transport->get(Transport::VERSION_PATH), '');
    }

    /**
     * @return array<string, string> ISO 3166-1 alpha-3 code keyed by country or zone name, e.g. "France" => "FRA".
     */
    public function countryCodes(): array
    {
        return $this->getStringMap('/v1/utils/country_code');
    }

    /**
     * @return list<CloudRegion>
     */
    public function cloudRegions(?string $provider = null): array
    {
        $regions = [];
        foreach (Payload::list($this->transport->get('/v1/utils/cloud_regions', ['provider' => $provider]), '') as $i => $region) {
            $regions[] = CloudRegion::fromArray(Payload::object($region, (string) $i), (string) $i);
        }

        return $regions;
    }

    /**
     * @return list<string>
     */
    public function cpuFamilies(): array
    {
        return $this->getStringList('/v1/utils/cpu_family');
    }

    /**
     * @return list<string>
     */
    public function cpuModelRanges(): array
    {
        return $this->getStringList('/v1/utils/cpu_model_range');
    }

    /**
     * @return list<string>
     */
    public function cpuNames(): array
    {
        return $this->getStringList('/v1/utils/cpu_name');
    }

    /**
     * Resolves a free-text CPU name to its specifications (fuzzy match).
     */
    public function nameToCpu(string $cpuName): CpuSpecification
    {
        return CpuSpecification::fromArray($this->getArray('/v1/utils/name_to_cpu', ['cpu_name' => $cpuName]));
    }

    /**
     * @return list<string>
     */
    public function gpuNames(): array
    {
        return $this->getStringList('/v1/utils/gpu_name');
    }

    /**
     * Resolves a free-text GPU name to its specifications (fuzzy match).
     */
    public function nameToGpu(string $gpuName): GpuSpecification
    {
        return GpuSpecification::fromArray($this->getArray('/v1/utils/name_to_gpu', ['gpu_name' => $gpuName]));
    }

    /**
     * @return list<string>
     */
    public function ssdManufacturers(): array
    {
        return $this->getStringList('/v1/utils/ssd_manufacturer');
    }

    /**
     * @return list<string>
     */
    public function ramManufacturers(): array
    {
        return $this->getStringList('/v1/utils/ram_manufacturer');
    }

    /**
     * @return list<string>
     */
    public function caseTypes(): array
    {
        return $this->getStringList('/v1/utils/case_type');
    }

    /**
     * @return array<string, ImpactCriterion> Keyed by criterion name.
     */
    public function impactCriteria(): array
    {
        $criteria = [];
        foreach (Payload::object($this->transport->get('/v1/utils/impact_criteria'), '') as $name => $criterion) {
            $criteria[(string) $name] = ImpactCriterion::fromArray(Payload::object($criterion, (string) $name), (string) $name);
        }

        return $criteria;
    }
}
