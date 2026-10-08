<?php

declare(strict_types=1);

namespace Boavizta\Api\Tests\Unit;

use Boavizta\Api\Exception\UnexpectedResponseException;
use Boavizta\Api\Response\ArchetypeConfig;
use Boavizta\Api\Response\CpuConsumptionProfile;
use Boavizta\Api\Response\CpuSpecification;
use Boavizta\Api\Response\ImpactCriterion;
use Boavizta\Api\Response\ImpactResult;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A missing key or a wrong type throws, naming the path; nothing silently becomes null, '' or [].
 */
final class ResponseHydrationTest extends MockClientTestCase
{
    private const PHASE = ['value' => 10, 'min' => 8, 'max' => 12];
    private const CRITERION = ['unit' => 'kgCO2eq', 'description' => 'Total climate change', 'embedded' => self::PHASE, 'use' => 'not implemented'];

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function brokenImpactResponses(): iterable
    {
        $without = static fn (array $a, string $key): array => array_diff_key($a, [$key => true]);

        yield 'no impacts' => [['warnings' => []], '"impacts" is missing'];
        yield 'impacts is a list' => [['impacts' => [1, 2]], '"impacts" should be an object'];
        yield 'no unit' => [['impacts' => ['gwp' => $without(self::CRITERION, 'unit')]], '"impacts.gwp.unit" is missing'];
        yield 'unit is a number' => [['impacts' => ['gwp' => ['unit' => 1] + self::CRITERION]], '"impacts.gwp.unit" should be a string'];
        yield 'no description' => [['impacts' => ['gwp' => $without(self::CRITERION, 'description')]], '"impacts.gwp.description" is missing'];
        yield 'no use phase' => [['impacts' => ['gwp' => $without(self::CRITERION, 'use')]], '"impacts.gwp.use" is missing'];
        yield 'phase is another string' => [['impacts' => ['gwp' => ['use' => 'n/a'] + self::CRITERION]], '"impacts.gwp.use" should be an object or "not implemented"'];
        yield 'phase is null' => [['impacts' => ['gwp' => ['use' => null] + self::CRITERION]], '"impacts.gwp.use" should be an object or "not implemented"'];
        yield 'no value' => [['impacts' => ['gwp' => ['embedded' => $without(self::PHASE, 'value')] + self::CRITERION]], '"impacts.gwp.embedded.value" is missing'];
        yield 'no min' => [['impacts' => ['gwp' => ['embedded' => $without(self::PHASE, 'min')] + self::CRITERION]], '"impacts.gwp.embedded.min" is missing'];
        yield 'value is a string' => [['impacts' => ['gwp' => ['embedded' => ['value' => '10'] + self::PHASE] + self::CRITERION]], '"impacts.gwp.embedded.value" should be a number'];
        yield 'warnings not strings' => [['impacts' => ['gwp' => ['embedded' => ['warnings' => [1]] + self::PHASE] + self::CRITERION]], '"impacts.gwp.embedded.warnings.0" should be a string'];
        yield 'global warnings not a list' => [['impacts' => [], 'warnings' => 'oops'], '"warnings" should be a list'];
    }

    /**
     * @param array<mixed> $response
     */
    #[DataProvider('brokenImpactResponses')]
    public function testBrokenImpactResponseThrows(array $response, string $message): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage($message);
        ImpactResult::fromArray($response);
    }

    public function testOptionalKeysAreOnlyThoseTheApiOmits(): void
    {
        $result = ImpactResult::fromArray(['impacts' => ['gwp' => self::CRITERION]]);

        self::assertSame([], $result->warnings, 'Omitted by the API when there is none');
        self::assertNull($result->verbose, 'Only sent with verbose=true');
        self::assertSame([], $result->impacts['gwp']->embedded?->warnings, 'Omitted by the API when there is none');
        self::assertNull($result->impacts['gwp']->use, '"not implemented"');
    }

    public function testImpactEndpointRejectsBrokenResponse(): void
    {
        $this->queueJson(['impacts' => ['gwp' => ['unit' => 'kgCO2eq']]]);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('"impacts.gwp.description" is missing');
        $this->client->server()->impactFromArchetype('dellR740');
    }

    public function testVersionMustBeAString(): void
    {
        $this->queueJson(['version' => '2.4.1']);

        $this->expectException(UnexpectedResponseException::class);
        $this->client->utils()->version();
    }

    public function testStringListRejectsOtherItems(): void
    {
        $this->queueJson(['dellR740', null]);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('"1" should be a string');
        $this->client->server()->archetypes();
    }

    public function testImpactCriterionRequiresMethodKeyEvenWhenNull(): void
    {
        self::assertNull(ImpactCriterion::fromArray(['name' => 'gwp', 'unit' => 'kgCO2eq', 'description' => 'Climate', 'method' => null])->method);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('"gwp.method" is missing');
        ImpactCriterion::fromArray(['name' => 'gwp', 'unit' => 'kgCO2eq', 'description' => 'Climate'], 'gwp');
    }

    public function testConsumptionProfileRequiresEveryCoefficient(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('"d" is missing');
        CpuConsumptionProfile::fromArray(['a' => 1, 'b' => 2, 'c' => 3]);
    }

    public function testConsumptionProfilePower(): void
    {
        $profile = new CpuConsumptionProfile(a: 35.5688, b: 0.2438, c: 9.6694, d: -0.6087);

        self::assertEqualsWithDelta(35.5688 * log(0.2438 * 59.6694) - 0.6087, $profile->power(50), 1e-9);
    }

    public function testCpuSpecificationRequiresKeysButAcceptsNullValues(): void
    {
        $data = ['name' => 'Intel Xeon Gold 6134', 'manufacturer' => 'Intel', 'model_range' => 'Xeon Gold', 'family' => 'Skylake', 'core_units' => null, 'die_size' => 694.0, 'die_size_per_core' => null, 'tdp' => null];
        $cpu = CpuSpecification::fromArray($data);

        self::assertNull($cpu->tdp);
        self::assertSame(['units' => 2, 'die_size' => 694.0, 'manufacturer' => 'Intel', 'model_range' => 'Xeon Gold', 'family' => 'Skylake', 'name' => 'Intel Xeon Gold 6134'], $cpu->toCpu(2)->toArray());

        unset($data['tdp']);
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('"tdp" is missing');
        CpuSpecification::fromArray($data);
    }

    public function testArchetypeConfigSplitsParametersAndGroups(): void
    {
        $config = ArchetypeConfig::fromArray([
            'manufacturer' => ['default' => 'Dell'],
            'CPU' => ['units' => ['default' => 2.0], 'name' => []],
            'USAGE' => ['time_workload' => ['default' => 50.0, 'min' => 0.0, 'max' => 100.0]],
        ]);

        self::assertSame(['manufacturer'], array_keys($config->parameters));
        self::assertSame(['CPU', 'USAGE'], array_keys($config->groups));
        self::assertSame('Dell', $config->default('manufacturer'));
        self::assertSame(100.0, $config->parameter('time_workload', 'USAGE')?->max);
        self::assertFalse($config->parameter('name', 'CPU')?->hasDefault);
        self::assertNull($config->parameter('unknown'));
    }

    public function testArchetypeParameterRejectsUnknownShape(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('"CPU.units"');
        ArchetypeConfig::fromArray(['CPU' => ['units' => ['value' => 2]]]);
    }
}
