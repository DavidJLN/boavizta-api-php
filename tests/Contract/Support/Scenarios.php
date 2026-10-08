<?php

declare(strict_types=1);

namespace Boavizta\Api\Tests\Contract\Support;

use Boavizta\Api\BoaviztaClient;
use Boavizta\Api\Dto\CloudInstance;
use Boavizta\Api\Dto\ConsumptionProfileCpu;
use Boavizta\Api\Dto\Cpu;
use Boavizta\Api\Dto\Disk;
use Boavizta\Api\Dto\Gpu;
use Boavizta\Api\Dto\PowerSupply;
use Boavizta\Api\Dto\Ram;
use Boavizta\Api\Dto\Server;
use Boavizta\Api\Dto\ServerConfiguration;
use Boavizta\Api\Dto\ServerModel;
use Boavizta\Api\Dto\Usage;
use Boavizta\Api\Dto\UsageCloud;
use Boavizta\Api\Dto\UserTerminal;
use Boavizta\Api\Enum\ComponentType;
use Boavizta\Api\Enum\Criterion;
use Boavizta\Api\Enum\DiskType;
use Boavizta\Api\Enum\PeripheralType;
use Boavizta\Api\Enum\TerminalType;
use Boavizta\Api\Exception\BoaviztaException;
use Boavizta\Api\Exception\NotFoundException;
use Boavizta\Api\Exception\ServerException;
use Boavizta\Api\Request\ImpactOptions;
use Boavizta\Api\Response\ArchetypeConfig;
use Boavizta\Api\Response\ArchetypeParameter;
use Boavizta\Api\Response\CloudRegion;
use Boavizta\Api\Response\CpuConsumptionProfile;
use Boavizta\Api\Response\CpuSpecification;
use Boavizta\Api\Response\GpuSpecification;
use Boavizta\Api\Response\ImpactCriterion;
use Boavizta\Api\Response\ImpactResult;
use PHPUnit\Framework\Assert;

/**
 * The requests shared by the contract tests (replayed offline) and the live tests (sent to the real API).
 *
 * Each scenario sends exactly one request. Its check receives what the client returned (or the
 * BoaviztaException it threw) and the decoded body the API answered, so the same check holds
 * against the recording and against today's server.
 */
final class Scenarios
{
    /**
     * @return array<string, Scenario>
     */
    public static function all(): array
    {
        $scenarios = [
            new Scenario('utils_version', static fn (BoaviztaClient $c) => $c->utils()->version(), static function (mixed $version, mixed $body): void {
                Assert::assertIsString($version);
                Assert::assertSame($body, $version);
                Assert::assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', $version);
            }),

            new Scenario('utils_impact_criteria', static fn (BoaviztaClient $c) => $c->utils()->impactCriteria(), static function (mixed $criteria, mixed $body): void {
                Assert::assertIsArray($criteria);
                Assert::assertIsArray($body);
                foreach ($body as $name => $raw) {
                    $criterion = $criteria[$name] ?? null;
                    Assert::assertInstanceOf(ImpactCriterion::class, $criterion);
                    Assert::assertSame([$raw['name'], $raw['unit'], $raw['description'], $raw['method']], [$criterion->name, $criterion->unit, $criterion->description, $criterion->method]);
                }
                $known = array_map(static fn (Criterion $c): string => $c->value, array_filter(Criterion::cases(), static fn (Criterion $c): bool => $c !== Criterion::All));
                $sent = array_map('strval', array_keys($criteria));
                sort($known);
                sort($sent);
                Assert::assertSame($known, $sent, 'The Criterion enum must list exactly the criteria the API supports');
            }),

            new Scenario('cloud_providers', static fn (BoaviztaClient $c) => $c->cloud()->providers(), static function (mixed $providers): void {
                Assert::assertIsArray($providers);
                Assert::assertContains('aws', $providers);
            }),

            new Scenario(
                'cloud_instance_fuzzy_match',
                static fn (BoaviztaClient $c) => $c->cloud()->instanceImpact('aws', 'a1.4xlarg', new ImpactOptions(criteria: [Criterion::Gwp], duration: 8760)),
                static function (mixed $result, mixed $body): void {
                    $result = self::impactResult($result, $body);
                    Assert::assertSame(['gwp'], array_keys($result->impacts));
                    Assert::assertCount(1, $result->warnings);
                    Assert::assertStringContainsString("'a1.4xlarge'", $result->warnings[0]);
                },
            ),

            new Scenario(
                'cloud_instance_verbose',
                static fn (BoaviztaClient $c) => $c->cloud()->instanceImpact('aws', 'a1.4xlarge', new ImpactOptions(criteria: [Criterion::Gwp], verbose: true)),
                static function (mixed $result, mixed $body): void {
                    $result = self::impactResult($result, $body);
                    Assert::assertNotNull($result->verbose);
                    Assert::assertNotEmpty($result->verbose);
                },
                // The server fits the CPU consumption curve numerically at each call; the coefficients
                // differ from one call to the next in their 4th significant digit (measured 2026-10-07).
                unstablePaths: ['verbose.CPU-1.params.value'],
            ),

            new Scenario(
                'cloud_instance_custom_usage',
                static fn (BoaviztaClient $c) => $c->cloud()->instanceImpactFromConfiguration(
                    new CloudInstance(provider: 'aws', instanceType: 'a1.4xlarge', usage: new UsageCloud(usageLocation: 'FRA', timeWorkload: 50)),
                    new ImpactOptions(criteria: [Criterion::Gwp, Criterion::Pe], duration: 8760),
                ),
                static function (mixed $result, mixed $body): void {
                    $result = self::impactResult($result, $body);
                    Assert::assertSame('kgCO2eq', $result->impact(Criterion::Gwp)?->unit);
                    Assert::assertSame('MJ', $result->impact(Criterion::Pe)?->unit);
                },
            ),

            new Scenario('server_archetypes', static fn (BoaviztaClient $c) => $c->server()->archetypes(), static function (mixed $archetypes): void {
                Assert::assertIsArray($archetypes);
                Assert::assertContains('dellR740', $archetypes);
            }),

            new Scenario('server_archetype_config', static fn (BoaviztaClient $c) => $c->server()->archetypeConfig('dellR740'), static function (mixed $config, mixed $body): void {
                $config = self::archetypeConfig($config, $body);
                Assert::assertSame('Dell', $config->default('manufacturer'));
                Assert::assertSame(2.0, $config->default('units', 'CPU'));
                Assert::assertSame(5.0, $config->parameter('unit_weight', 'POWER_SUPPLY')?->max);
                Assert::assertFalse($config->parameter('name', 'CPU')?->hasDefault, 'An empty object is a parameter without default');
            }),

            new Scenario('terminal_laptop_archetype_config', static fn (BoaviztaClient $c) => $c->terminal()->archetypeConfig(TerminalType::Laptop, 'laptop-pro'), static function (mixed $config, mixed $body): void {
                Assert::assertSame(35040.0, self::archetypeConfig($config, $body)->default('hours_life_time', 'USAGE'));
            }),

            new Scenario('iot_archetype_config', static fn (BoaviztaClient $c) => $c->iot()->archetypeConfig('iot-device-default'), static function (mixed $config, mixed $body): void {
                Assert::assertSame([], self::archetypeConfig($config, $body)->parameters);
            }),

            new Scenario('cloud_instance_config', static fn (BoaviztaClient $c) => $c->cloud()->instanceConfig('aws', 'a1.4xlarge'), static function (mixed $config, mixed $body): void {
                $config = self::archetypeConfig($config, $body);
                Assert::assertSame(16.0, $config->default('vcpu'));
                Assert::assertSame([], $config->groups);
            }),

            new Scenario('component_all', static fn (BoaviztaClient $c) => $c->component()->all(), static function (mixed $categories, mixed $body): void {
                Assert::assertSame($body, $categories);
                Assert::assertSame('v1/component/cpu', $categories['cpu'] ?? null);
            }),

            new Scenario('terminal_all', static fn (BoaviztaClient $c) => $c->terminal()->all(), static function (mixed $categories, mixed $body): void {
                Assert::assertSame($body, $categories);
                Assert::assertIsArray($categories);
                $known = array_map(static fn (TerminalType $t): string => $t->value, TerminalType::cases());
                $sent = array_keys($categories);
                sort($known);
                sort($sent);
                Assert::assertSame($known, $sent, 'The TerminalType enum must list exactly the terminals the API supports');
            }),

            new Scenario('peripheral_all', static fn (BoaviztaClient $c) => $c->peripheral()->all(), static function (mixed $categories, mixed $body): void {
                Assert::assertSame($body, $categories);
                Assert::assertIsArray($categories);
                $known = array_map(static fn (PeripheralType $t): string => $t->value, PeripheralType::cases());
                $sent = array_keys($categories);
                sort($known);
                sort($sent);
                Assert::assertSame($known, $sent, 'The PeripheralType enum must list exactly the peripherals the API supports');
            }),

            new Scenario(
                'server_impact_from_archetype',
                static fn (BoaviztaClient $c) => $c->server()->impactFromArchetype('dellR740', new ImpactOptions(criteria: [Criterion::Gwp, Criterion::Adp, Criterion::Pe])),
                static function (mixed $result, mixed $body): void {
                    $result = self::impactResult($result, $body);
                    Assert::assertSame(['gwp', 'adp', 'pe'], array_keys($result->impacts));
                    foreach ($result->impacts as $impact) {
                        Assert::assertNotNull($impact->embedded, "$impact->criterion.embedded");
                        Assert::assertNotNull($impact->use, "$impact->criterion.use");
                    }
                },
            ),

            new Scenario(
                // Same payload as boaviztapi tests/api/test_server.py::test_complete_config_server
                'server_impact_from_configuration',
                static fn (BoaviztaClient $c) => $c->server()->impactFromConfiguration(new Server(
                    model: new ServerModel(),
                    configuration: new ServerConfiguration(
                        cpu: new Cpu(units: 2, coreUnits: 24, dieSizePerCore: 24.5),
                        ram: [new Ram(units: 4, capacity: 32, density: 1.79), new Ram(units: 4, capacity: 16, density: 1.79)],
                        disk: [new Disk(units: 2, type: DiskType::Ssd, capacity: 400, density: 50.6), new Disk(units: 2, type: DiskType::Hdd)],
                        gpu: new Gpu(units: 2, vram: 40),
                        powerSupply: new PowerSupply(units: 2, unitWeight: 10),
                    ),
                )),
                static function (mixed $result, mixed $body): void {
                    $gwp = self::impactResult($result, $body)->impact(Criterion::Gwp);
                    Assert::assertNotNull($gwp);
                    Assert::assertSame(1791.0, $gwp->embedded?->value, 'Value asserted by the upstream test suite');
                    Assert::assertSame(7000.0, $gwp->use?->value, 'Value asserted by the upstream test suite');
                },
            ),

            new Scenario('server_unknown_archetype', static fn (BoaviztaClient $c) => $c->server()->archetypeConfig('does-not-exist'), static function (mixed $error, mixed $body): void {
                Assert::assertInstanceOf(NotFoundException::class, $error);
                Assert::assertSame(404, $error->getCode());
                Assert::assertSame('does-not-exist not found', $error->getMessage());
                Assert::assertSame($body, $error->body);
            }),

            new Scenario(
                // The API answers an unknown criterion with a bare, non-JSON HTTP 500.
                'server_unknown_criterion',
                static fn (BoaviztaClient $c) => $c->server()->impactFromArchetype('dellR740', new ImpactOptions(criteria: ['not-a-criterion'])),
                static function (mixed $error, mixed $body): void {
                    Assert::assertInstanceOf(ServerException::class, $error);
                    Assert::assertSame(500, $error->getCode());
                    Assert::assertFalse($error->isRetryable(), 'The same request will fail the same way');
                    Assert::assertNull($error->body);
                    Assert::assertNull($body);
                },
            ),

            new Scenario('component_cpu_archetypes', static fn (BoaviztaClient $c) => $c->component()->archetypes(ComponentType::Cpu), static function (mixed $archetypes): void {
                Assert::assertIsArray($archetypes);
                Assert::assertNotEmpty($archetypes);
            }),

            new Scenario(
                'component_cpu_by_name',
                static fn (BoaviztaClient $c) => $c->component()->impact(new Cpu(name: 'AMD EPYC 7763'), new ImpactOptions(criteria: [Criterion::Gwp], duration: 8760)),
                static function (mixed $result, mixed $body): void {
                    Assert::assertGreaterThan(0, self::impactResult($result, $body)->impact(Criterion::Gwp)?->embedded?->value);
                },
            ),

            new Scenario(
                'component_hdd',
                static fn (BoaviztaClient $c) => $c->component()->impact(new Disk(type: DiskType::Hdd, capacity: 2000), new ImpactOptions(criteria: [Criterion::Gwp])),
                static function (mixed $result, mixed $body): void {
                    $gwp = self::impactResult($result, $body)->impact(Criterion::Gwp);
                    Assert::assertGreaterThan(0, $gwp?->embedded?->value);
                    Assert::assertNull($gwp?->use, 'The API answers "not implemented" for the use phase of a disk');
                    Assert::assertSame($gwp?->embedded?->value, $gwp?->total(), 'total() ignores the unimplemented phase');
                },
            ),

            new Scenario(
                'terminal_laptop',
                static fn (BoaviztaClient $c) => $c->terminal()->impact(
                    TerminalType::Laptop,
                    new UserTerminal(usage: new Usage(useTimeRatio: 8 / 24, usageLocation: 'DEU')),
                    new ImpactOptions(criteria: [Criterion::Gwp, Criterion::Pe], duration: 8760),
                ),
                static function (mixed $result, mixed $body): void {
                    $result = self::impactResult($result, $body);
                    $gwp = $result->impact(Criterion::Gwp);
                    Assert::assertGreaterThan(0, $gwp?->embedded?->value);
                    Assert::assertGreaterThan(0, $gwp?->use?->value);
                    Assert::assertNull($result->impact(Criterion::Pe)?->embedded, 'The API answers "not implemented" for the embedded pe of a laptop');
                },
            ),

            new Scenario('peripheral_monitor_archetypes', static fn (BoaviztaClient $c) => $c->peripheral()->archetypes(PeripheralType::Monitor), static function (mixed $archetypes): void {
                Assert::assertIsArray($archetypes);
                Assert::assertNotEmpty($archetypes);
            }),

            new Scenario('iot_archetypes', static fn (BoaviztaClient $c) => $c->iot()->archetypes(), static function (mixed $archetypes): void {
                Assert::assertIsArray($archetypes);
                Assert::assertContains('iot-device-default', $archetypes);
            }),

            new Scenario(
                'iot_default_impact',
                static fn (BoaviztaClient $c) => $c->iot()->impactFromArchetype(options: new ImpactOptions(criteria: [Criterion::Gwp])),
                static function (mixed $result, mixed $body): void {
                    Assert::assertTrue(self::impactResult($result, $body)->has(Criterion::Gwp));
                },
            ),

            new Scenario(
                'consumption_profile_cpu',
                static fn (BoaviztaClient $c) => $c->consumptionProfile()->cpu(new ConsumptionProfileCpu(cpu: new Cpu(name: 'Intel Xeon Gold 6134'))),
                static function (mixed $profile, mixed $body): void {
                    Assert::assertInstanceOf(CpuConsumptionProfile::class, $profile);
                    Assert::assertIsArray($body);
                    Assert::assertSame(['a', 'b', 'c', 'd'], array_keys($body));
                    Assert::assertSame(array_map('floatval', $body), ['a' => $profile->a, 'b' => $profile->b, 'c' => $profile->c, 'd' => $profile->d]);
                },
            ),

            new Scenario('utils_name_to_cpu', static fn (BoaviztaClient $c) => $c->utils()->nameToCpu('Intel Xeon Gold 6134'), static function (mixed $cpu, mixed $body): void {
                Assert::assertInstanceOf(CpuSpecification::class, $cpu);
                Assert::assertIsArray($body);
                Assert::assertSame(
                    [$body['name'], $body['manufacturer'], $body['model_range'], $body['family'], $body['core_units'], $body['die_size'], $body['die_size_per_core'], $body['tdp']],
                    [$cpu->name, $cpu->manufacturer, $cpu->modelRange, $cpu->family, $cpu->coreUnits, $cpu->dieSize, $cpu->dieSizePerCore, $cpu->tdp],
                );
                Assert::assertSame('Intel Xeon Gold 6134', $cpu->name);
            }),

            new Scenario('utils_name_to_gpu', static fn (BoaviztaClient $c) => $c->utils()->nameToGpu('NVIDIA A100'), static function (mixed $gpu, mixed $body): void {
                Assert::assertInstanceOf(GpuSpecification::class, $gpu);
                Assert::assertIsArray($body);
                Assert::assertSame('NVIDIA A100 PCIe 40GB', $gpu->name);
                // Every attribute the API sends, except the request-only `units` and `usage`, is read and unbent.
                $read = $gpu->toGpu()->toArray();
                foreach (array_diff_key($body, ['units' => true, 'usage' => true]) as $key => $value) {
                    Assert::assertEquals($value, $read[$key] ?? null, $key);
                }
            }),

            new Scenario('utils_country_codes', static fn (BoaviztaClient $c) => $c->utils()->countryCodes(), static function (mixed $codes, mixed $body): void {
                Assert::assertSame($body, $codes);
                Assert::assertSame('FRA', $codes['France'] ?? null, 'Keyed by name, not by code');
            }),

            new Scenario('utils_cloud_regions', static fn (BoaviztaClient $c) => $c->utils()->cloudRegions('aws'), static function (mixed $regions, mixed $body): void {
                Assert::assertIsArray($regions);
                Assert::assertContainsOnlyInstancesOf(CloudRegion::class, $regions);
                Assert::assertSame($body, array_map(static fn (CloudRegion $r): array => ['provider' => $r->provider, 'region' => $r->region], $regions));
                Assert::assertContains('eu-west-3', array_column($body, 'region'));
            }),
        ];

        return array_column(array_map(static fn (Scenario $s): array => [$s->name, $s], $scenarios), 1, 0);
    }

    /**
     * Asserts the client turned the API's body into an ImpactResult without losing or bending anything,
     * then returns it for scenario-specific checks.
     */
    public static function impactResult(mixed $result, mixed $body): ImpactResult
    {
        if ($result instanceof BoaviztaException) {
            throw $result;
        }
        Assert::assertInstanceOf(ImpactResult::class, $result);
        Assert::assertIsArray($body);
        Assert::assertSame($body, $result->raw);

        $impacts = $body['impacts'] ?? null;
        Assert::assertIsArray($impacts, 'An impact response always has an "impacts" object');
        Assert::assertSame(array_keys($impacts), array_keys($result->impacts));

        foreach ($impacts as $criterion => $data) {
            Assert::assertIsArray($data);
            $impact = $result->impacts[$criterion];
            Assert::assertSame($data['unit'], $impact->unit, "$criterion.unit");
            Assert::assertSame($data['description'], $impact->description, "$criterion.description");

            foreach (['embedded' => $impact->embedded, 'use' => $impact->use] as $phase => $parsed) {
                $raw = $data[$phase] ?? null;
                if ($raw === 'not implemented') {
                    Assert::assertNull($parsed, "$criterion.$phase");
                    continue;
                }
                Assert::assertIsArray($raw, "$criterion.$phase is either an object or \"not implemented\"");
                Assert::assertNotNull($parsed, "$criterion.$phase was sent but not parsed");
                Assert::assertSame((float) $raw['value'], $parsed->value, "$criterion.$phase.value");
                Assert::assertSame((float) $raw['min'], $parsed->min, "$criterion.$phase.min");
                Assert::assertSame((float) $raw['max'], $parsed->max, "$criterion.$phase.max");
                Assert::assertSame($raw['warnings'] ?? [], $parsed->warnings, "$criterion.$phase.warnings");
            }
        }

        Assert::assertSame($body['warnings'] ?? [], $result->warnings);
        Assert::assertSame($body['verbose'] ?? null, $result->verbose);

        return $result;
    }

    /**
     * Asserts every node of the API's archetype configuration is in the ArchetypeConfig, unbent, then returns it.
     */
    public static function archetypeConfig(mixed $config, mixed $body): ArchetypeConfig
    {
        if ($config instanceof BoaviztaException) {
            throw $config;
        }
        Assert::assertInstanceOf(ArchetypeConfig::class, $config);
        Assert::assertIsArray($body);

        $rebuilt = [];
        foreach ($config->parameters as $name => $parameter) {
            $rebuilt[$name] = self::parameterAsArray($parameter);
        }
        foreach ($config->groups as $group => $parameters) {
            $rebuilt[$group] = array_map(self::parameterAsArray(...), $parameters);
        }
        Assert::assertEquals($body, $rebuilt);

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    private static function parameterAsArray(ArchetypeParameter $parameter): array
    {
        return array_filter(
            ['default' => $parameter->default, 'min' => $parameter->min, 'max' => $parameter->max],
            static fn (string $key): bool => $key === 'default' ? $parameter->hasDefault : $parameter->{$key} !== null,
            ARRAY_FILTER_USE_KEY,
        );
    }
}
