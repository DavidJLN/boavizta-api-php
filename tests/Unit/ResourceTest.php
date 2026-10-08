<?php

declare(strict_types=1);

namespace Boavizta\Api\Tests\Unit;

use Boavizta\Api\Dto\Cpu;
use Boavizta\Api\Dto\Disk;
use Boavizta\Api\Dto\IotDevice;
use Boavizta\Api\Dto\Server;
use Boavizta\Api\Dto\ServerConfiguration;
use Boavizta\Api\Dto\Usage;
use Boavizta\Api\Dto\UserTerminal;
use Boavizta\Api\Enum\ComponentType;
use Boavizta\Api\Enum\Criterion;
use Boavizta\Api\Enum\DiskType;
use Boavizta\Api\Enum\PeripheralType;
use Boavizta\Api\Enum\TerminalType;
use Boavizta\Api\Request\ImpactOptions;

final class ResourceTest extends MockClientTestCase
{
    /**
     * Response taken from boaviztapi tests/api/test_server.py::test_complete_config_server.
     */
    private const SERVER_RESPONSE = [
        'impacts' => [
            'gwp' => [
                'description' => 'Total climate change',
                'embedded' => ['max' => 1791.0, 'min' => 1727.0, 'value' => 1791.0, 'warnings' => ['End of life is not included in the calculation']],
                'unit' => 'kgCO2eq',
                'use' => ['max' => 36240.0, 'min' => 101.3, 'value' => 7000.0],
            ],
            'adp' => [
                'description' => 'Use of minerals and fossil ressources',
                'embedded' => ['max' => 0.2727, 'min' => 0.2652, 'value' => 0.2652],
                'unit' => 'kgSbeq',
                'use' => 'not implemented',
            ],
        ],
    ];

    public function testServerImpactFromConfiguration(): void
    {
        $this->queueJson(self::SERVER_RESPONSE);

        $result = $this->client->server()->impactFromConfiguration(
            new Server(configuration: new ServerConfiguration(cpu: new Cpu(units: 2, coreUnits: 24))),
            new ImpactOptions(criteria: [Criterion::Gwp, Criterion::Adp]),
            'dellR740',
        );

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/v1/server/', $request->getUri()->getPath());
        self::assertSame('verbose=false&archetype=dellR740&criteria=gwp&criteria=adp', $request->getUri()->getQuery());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame(['configuration' => ['cpu' => ['units' => 2, 'core_units' => 24]]], $this->lastJsonBody());

        $gwp = $result->impact(Criterion::Gwp);
        self::assertNotNull($gwp);
        self::assertSame('kgCO2eq', $gwp->unit);
        self::assertSame(1791.0, $gwp->embedded?->value);
        self::assertSame(1727.0, $gwp->embedded->min);
        self::assertSame(['End of life is not included in the calculation'], $gwp->embedded->warnings);
        self::assertSame(101.3, $gwp->use?->min);
        self::assertSame(8791.0, $gwp->total());

        $adp = $result->impact('adp');
        self::assertNotNull($adp);
        self::assertNull($adp->use, '"not implemented" phases are exposed as null');
        self::assertFalse($result->has(Criterion::Pe));
        self::assertNull($result->verbose);
    }

    public function testCloudInstanceImpactExposesFuzzyMatchWarning(): void
    {
        $this->queueJson(['impacts' => [], 'verbose' => ['units' => 1], 'warnings' => ["Instance 'a1.4xlarg' not found; using closest match 'a1.4xlarge' (fuzzy match)"]]);

        $result = $this->client->cloud()->instanceImpact('aws', 'a1.4xlarg', new ImpactOptions(duration: 8760, verbose: true));

        self::assertSame('GET', $this->lastRequest()->getMethod());
        self::assertSame('/v1/cloud/instance', $this->lastRequest()->getUri()->getPath());
        self::assertSame('verbose=true&duration=8760&provider=aws&instance_type=a1.4xlarg', $this->lastRequest()->getUri()->getQuery());
        self::assertCount(1, $result->warnings);
        self::assertSame(['units' => 1], $result->verbose);
    }

    public function testDiskIsRoutedToItsTypeEndpoint(): void
    {
        $this->queueJson(['impacts' => []]);

        $this->client->component()->impact(new Disk(type: DiskType::Hdd, capacity: 2000));

        self::assertSame('/v1/component/hdd', $this->lastRequest()->getUri()->getPath());
    }

    public function testDiskWithoutTypeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client->component()->impact(new Disk(capacity: 2000));
    }

    public function testComponentArchetypesUsesSingularPath(): void
    {
        $this->queueJson(['intel_xeon_gold_6134']);

        self::assertSame(['intel_xeon_gold_6134'], $this->client->component()->archetypes(ComponentType::Cpu));
        self::assertSame('/v1/component/cpu/archetype', $this->lastRequest()->getUri()->getPath());
    }

    public function testTerminalAndPeripheralPaths(): void
    {
        $this->queueJson(['impacts' => []]);
        $this->client->terminal()->impact(
            TerminalType::Laptop,
            new UserTerminal(usage: new Usage(usageLocation: 'FRA', hoursLifeTime: 26280)),
            archetype: 'laptop-pro',
        );
        self::assertSame('/v1/terminal/laptop', $this->lastRequest()->getUri()->getPath());
        self::assertSame(['usage' => ['hours_life_time' => 26280.0, 'usage_location' => 'FRA']], $this->lastJsonBody());

        $this->queueJson(['dell-p2419h']);
        $this->client->peripheral()->archetypes(PeripheralType::Monitor);
        self::assertSame('/v1/peripheral/monitor/archetypes', $this->lastRequest()->getUri()->getPath());
    }

    public function testEmptyBodyIsSentAsJsonObject(): void
    {
        $this->queueJson(['impacts' => []]);

        $this->client->iot()->impact(new IotDevice());

        self::assertSame('{}', (string) $this->lastRequest()->getBody());
    }
}
