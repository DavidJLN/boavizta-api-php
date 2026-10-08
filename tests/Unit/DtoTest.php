<?php

declare(strict_types=1);

namespace Boavizta\Api\Tests\Unit;

use Boavizta\Api\Dto\CloudInstance;
use Boavizta\Api\Dto\Cpu;
use Boavizta\Api\Dto\Disk;
use Boavizta\Api\Dto\ElecFactors;
use Boavizta\Api\Dto\PowerSupply;
use Boavizta\Api\Dto\Ram;
use Boavizta\Api\Dto\Server;
use Boavizta\Api\Dto\ServerConfiguration;
use Boavizta\Api\Dto\ServerModel;
use Boavizta\Api\Dto\UsageCloud;
use Boavizta\Api\Dto\WorkloadTime;
use Boavizta\Api\Enum\DiskType;
use PHPUnit\Framework\TestCase;

final class DtoTest extends TestCase
{
    public function testServerSerializesToSnakeCaseAndDropsNulls(): void
    {
        $server = new Server(
            model: new ServerModel(),
            configuration: new ServerConfiguration(
                cpu: new Cpu(units: 2, coreUnits: 24, dieSizePerCore: 24.5),
                ram: [new Ram(units: 4, capacity: 32, density: 1.79)],
                disk: [new Disk(units: 2, type: DiskType::Ssd, capacity: 400)],
                powerSupply: new PowerSupply(units: 2, unitWeight: 10.0),
            ),
        );

        self::assertSame(
            '{"model":{},"configuration":{"cpu":{"units":2,"core_units":24,"die_size_per_core":24.5},'
            . '"ram":[{"units":4,"capacity":32,"density":1.79}],'
            . '"disk":[{"units":2,"type":"ssd","capacity":400}],'
            . '"power_supply":{"units":2,"unit_weight":10.0}}}',
            json_encode($server, JSON_PRESERVE_ZERO_FRACTION),
        );
    }

    public function testCloudUsageWithWorkloadProfileAndElecFactors(): void
    {
        $instance = new CloudInstance(
            provider: 'aws',
            instanceType: 'a1.4xlarge',
            usage: new UsageCloud(
                timeWorkload: [new WorkloadTime(timePercentage: 50, loadPercentage: 0), new WorkloadTime(50, 100)],
                elecFactors: new ElecFactors(gwp: 0.05, ctuhC: 1e-9),
                instancePerServer: 2,
                region: 'eu-west-3',
            ),
        );

        self::assertSame([
            'provider' => 'aws',
            'instance_type' => 'a1.4xlarge',
            'usage' => [
                'time_workload' => [
                    ['time_percentage' => 50.0, 'load_percentage' => 0.0],
                    ['time_percentage' => 50.0, 'load_percentage' => 100.0],
                ],
                'elec_factors' => ['gwp' => 0.05, 'ctuh_c' => 1e-9],
                'instance_per_server' => 2,
                'region' => 'eu-west-3',
            ],
        ], json_decode((string) json_encode($instance, JSON_PRESERVE_ZERO_FRACTION), true));
    }
}
