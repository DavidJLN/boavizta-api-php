<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Boavizta\Api\BoaviztaClient;
use Boavizta\Api\Dto\CloudInstance;
use Boavizta\Api\Dto\Cpu;
use Boavizta\Api\Dto\Ram;
use Boavizta\Api\Dto\Server;
use Boavizta\Api\Dto\ServerConfiguration;
use Boavizta\Api\Dto\Usage;
use Boavizta\Api\Dto\UsageCloud;
use Boavizta\Api\Dto\UsageServer;
use Boavizta\Api\Dto\UserTerminal;
use Boavizta\Api\Enum\Criterion;
use Boavizta\Api\Enum\TerminalType;
use Boavizta\Api\Request\ImpactOptions;
use Boavizta\Api\Response\ImpactResult;
use Boavizta\Api\Response\PhaseImpact;

$client = BoaviztaClient::create(getenv('BOAVIZTA_API_URL') ?: BoaviztaClient::DEFAULT_BASE_URI);
$oneYear = new ImpactOptions(criteria: [Criterion::Gwp, Criterion::Pe], duration: 8760);

function show(string $title, ImpactResult $result): void
{
    echo "== $title\n";
    $format = static fn (?PhaseImpact $phase): string => $phase === null ? 'n/a' : number_format($phase->value, 2, '.', '');
    foreach ($result->impacts as $impact) {
        printf(
            "  %-4s embedded %10s  use %10s  %s\n",
            $impact->criterion,
            $format($impact->embedded),
            $format($impact->use),
            $impact->unit,
        );
    }
    foreach ($result->warnings as $warning) {
        echo "  ! $warning\n";
    }
}

echo 'BoaviztAPI ', $client->utils()->version(), "\n";

show('Server, custom configuration, in France', $client->server()->impactFromConfiguration(
    new Server(
        configuration: new ServerConfiguration(
            cpu: new Cpu(units: 2, name: 'Intel Xeon Gold 6134'),
            ram: [new Ram(units: 12, capacity: 32)],
        ),
        usage: new UsageServer(usageLocation: 'FRA', timeWorkload: 50),
    ),
    $oneYear,
));

show('AWS a1.4xlarge, eu-west-3', $client->cloud()->instanceImpactFromConfiguration(
    new CloudInstance(provider: 'aws', instanceType: 'a1.4xlarge', usage: new UsageCloud(region: 'eu-west-3')),
    $oneYear,
));

show('Laptop (default archetype), used 8h/day in Germany', $client->terminal()->impact(
    TerminalType::Laptop,
    new UserTerminal(usage: new Usage(useTimeRatio: 8 / 24, usageLocation: 'DEU')),
    $oneYear,
));

show('CPU alone, by name', $client->component()->impact(new Cpu(name: 'AMD EPYC 7763'), $oneYear));
