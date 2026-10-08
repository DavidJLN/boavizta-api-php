# boaviztapi-php

PHP client for [BoaviztAPI](https://github.com/Boavizta/boaviztapi), Boavizta's API that computes
the multi-criteria environmental impact (climate, resources, energy, water…) of digital
equipment, over its **manufacturing** (`embedded`) and **use** (`use`) phases.

Tested with BoaviztAPI 2.4.1.

## Installation

```bash
composer require boavizta/boaviztapi-php
```

**This package does not pick your HTTP client, it uses yours.**

The library depends on PSR interfaces and on `php-http/discovery`. It imposes no implementation:

| Need | Interface | Status | Example implementations |
|---|---|---|---|
| Sending requests | PSR-18 `ClientInterface` | required | Guzzle, Symfony HttpClient, `php-http/curl-client`… |
| Requests and streams | PSR-17 `RequestFactoryInterface`, `StreamFactoryInterface` | required | `nyholm/psr7`, `guzzlehttp/psr7`, Laminas Diactoros… |
| Cache | PSR-6 `CacheItemPoolInterface` | optional | Symfony Cache, Laminas Cache, Stash… |
| Logging | PSR-3 `LoggerInterface` | optional | Monolog, or any PSR-3 logger |

#### Auto-discovery: a dependency, but not an HTTP client

Auto-discovery relies on `php-http/discovery`. It is the only production dependency besides the
PSR interfaces. It **installs no HTTP client**:

- if you explicitly pass a client and factories to `BoaviztaClient::create()`, it is not used;
- otherwise, it looks **among the packages your application already has** for a PSR-18 client
  and PSR-17 factories (Symfony HttpClient, Guzzle, Nyholm…) and uses them;
- if your application has none, it **fails with an explicit message**
  (`Boavizta\Api\Exception\ConfigurationException: No PSR-18 HTTP client found: install one…`),
  instead of installing one for you.

This package declares neither `psr/http-client-implementation` nor
`psr/http-factory-implementation` among its dependencies, so the `php-http/discovery` Composer
plugin has no reason to add a client to your project.

If your project has no client yet, install the one you prefer, for example:

```bash
composer require symfony/http-client nyholm/psr7
```

Cache and logging are injected when the client is created:

```php
$client = BoaviztaClient::create(
    httpClient: $psr18Client,       // optional: auto-discovered otherwise
    requestFactory: $psr17Factory,  // optional: auto-discovered otherwise
    streamFactory: $psr17Factory,   // optional: auto-discovered otherwise
    cache: $psr6Pool,               // optional: caches GET responses (reference data, archetypes)
    logger: $psr3Logger,            // optional: debug per call, warning on HTTP ≥ 400, error on network failure
    cacheTtl: 3600,                 // lifetime in seconds (default: 24 h, null = the pool decides)
    timeout: 10.0,                  // seconds (default: 30); only applies to the client built here
);
```

### Timeout

The client sets a default timeout of **30 seconds** (`BoaviztaClient::DEFAULT_TIMEOUT`). Without
one, an outage of the remote server would block your application indefinitely.

PSR-18 defines no timeout option. So when you do not pass a client, the library builds its own,
with that timeout, from the first implementation installed among Symfony HttpClient, Guzzle 7 and
`php-http/curl-client`. If none of the three is present, `create()` throws a
`ConfigurationException` instead of going on without a timeout. You can then pass your own client,
or `timeout: null` to explicitly accept having no timeout.

**If you pass your own client, its timeout is yours**: `timeout` does not apply to it. Configure it.

### Cache

The cache is **optional and disabled by default**. Only successful GET responses are cached.
Computations sent as POST and errors never are.

**The cache key contains the API version.** After a server upgrade, a response cached in the old
format is no longer found, instead of being served as if it were current. The server does not
announce its version in a header, so the client asks `/v1/utils/version` (never cached) on the
first GET that goes through the cache, then keeps it for the lifetime of the instance. This costs
one extra request per instance, and only when a cache is configured.

In a long-running process (queue worker, RoadRunner, FrankenPHP…), the version stays the one from
when the instance started. Recreate the client periodically if the server may change version while
the process runs.

A failing cache does not make the call fail. The error is logged as a `warning` and the request
goes to the server.

## Usage

```php
use Boavizta\Api\BoaviztaClient;
use Boavizta\Api\Dto\{Cpu, Ram, Server, ServerConfiguration, UsageServer};
use Boavizta\Api\Enum\Criterion;
use Boavizta\Api\Request\ImpactOptions;

$client = BoaviztaClient::create(); // https://api.boavizta.org by default, or your own instance

$result = $client->server()->impactFromConfiguration(
    new Server(
        configuration: new ServerConfiguration(
            cpu: new Cpu(units: 2, name: 'Intel Xeon Gold 6134'),
            ram: [new Ram(units: 12, capacity: 32)],
        ),
        usage: new UsageServer(usageLocation: 'FRA', timeWorkload: 50),
    ),
    new ImpactOptions(criteria: [Criterion::Gwp, Criterion::Pe], duration: 8760),
);

$gwp = $result->impact(Criterion::Gwp);
echo $gwp->embedded->value, ' ', $gwp->unit;   // manufacturing, amortized over 1 year
echo $gwp->use->min, ' - ', $gwp->use->max;     // uncertainty range
echo $gwp->total();
```

DTO fields left unset are not sent: the API completes them from the archetype (passed as the last
argument, e.g. `'dellR740'`).

### Resources

| Method | Endpoints |
|---|---|
| `server()` | `archetypes()`, `archetypeConfig()`, `impactFromArchetype()`, `impactFromConfiguration()` |
| `cloud()` | `providers()`, `instances($provider)`, `instanceConfig()`, `instanceImpact('aws', 'a1.4xlarge')`, `instanceImpactFromConfiguration(CloudInstance)` |
| `component()` | `all()`, `archetypes(ComponentType)`, `archetypeConfig()`, `impactFromArchetype()`, `impact(Cpu\|Gpu\|Ram\|Disk\|…)` |
| `terminal()` | same, with `TerminalType` (laptop, desktop, smartphone, tablet, television, box, vr_headset) and `UserTerminal` |
| `peripheral()` | same, with `PeripheralType` (monitor, usb_stick, external_ssd, external_hdd, vr_controller) |
| `iot()` | `archetypes()`, `archetypeConfig()`, `impactFromArchetype()`, `impact(IotDevice)` |
| `consumptionProfile()` | `cpu(ConsumptionProfileCpu)` |
| `utils()` | `version()`, `countryCodes()`, `cloudRegions()`, `cpuNames()`, `nameToCpu()`, `gpuNames()`, `nameToGpu()`, `impactCriteria()`… |

### Computation options (`ImpactOptions`)

- `criteria`: list of `Criterion` (server default: `gwp`, `adp`, `pe`; `Criterion::All` for all of them).
- `duration`: assessment duration in hours (default: the equipment's lifetime).
- `verbose`: `false` by default in this client (unlike the API); when `true`, the details of the
  assumptions are available, raw, in `$result->verbose`.

### Results

Every response is turned into typed objects by a factory that validates it. **A missing key, or
one of the wrong type, throws an `UnexpectedResponseException`** naming the offending path
(e.g. `"impacts.gwp.embedded.min" is missing`). Nothing silently becomes `null`, `''` or `[]`.
The only optional keys are those the API really omits: `warnings` when there are none, and
`verbose` outside verbose mode.

| Method | Returns |
|---|---|
| `impact…()` on every resource | `ImpactResult`: `impacts` (one `CriterionImpact` per criterion, with `embedded` and `use` as `PhaseImpact`), `verbose`, `warnings` (e.g. cloud instance type corrected by fuzzy matching), `raw` |
| `archetypeConfig()`, `cloud()->instanceConfig()` | `ArchetypeConfig`: `parameter($name, $group)`, `default($name, $group)` |
| `utils()->impactCriteria()` | `array<string, ImpactCriterion>` |
| `utils()->nameToCpu()` / `nameToGpu()` | `CpuSpecification` / `GpuSpecification`, with `toCpu()` / `toGpu()` to compute their impact |
| `utils()->cloudRegions()` | `list<CloudRegion>` |
| `utils()->countryCodes()` | `array<string, string>`, ISO 3166-1 alpha-3 code by country name (`'France' => 'FRA'`) |
| `consumptionProfile()->cpu()` | `CpuConsumptionProfile` (`a`, `b`, `c`, `d`, and `power($load)`) |
| `all()`, `archetypes()`, `…Names()` | validated arrays of strings |

A phase the API cannot compute (`"not implemented"`) is `null`.

### Errors

Only exceptions from `Boavizta\Api\Exception` leave the library, whatever PSR-18 client or PSR-6
pool is used. The original exception remains available through `getPrevious()`, but is never
thrown as is: your code does not depend on the implementation you picked.

| Exception | When | `isRetryable()` |
|---|---|---|
| `TransportException` | no usable response: network, timeout, body cut off | yes |
| `RateLimitException` | HTTP 429; `retryAfter()` in seconds when the server says so | yes |
| `ServerException` | HTTP 5xx; `retryAfter()` when the server says so | 502, 503, 504 only |
| `NotFoundException` | HTTP 404 (unknown archetype) | no |
| `ValidationException` | HTTP 422, with `errors()` | no |
| `UnexpectedResponseException` | 2xx response that breaks the contract (invalid JSON, missing key…) | no |
| `ConfigurationException` | at construction: no client found, timeout cannot be set | no |
| `BoaviztaException` | parent of them all, and other 4xx statuses | no |

A 500 is not retryable: the server did answer, and the same request will fail the same way
(BoaviztAPI returns one for an unknown criterion).

#### Retries

**The client never retries on its own.** An invisible retry multiplies the load on the provider at
the very moment it is struggling, and it is up to the application to decide whether it can wait.
`isRetryable()` and `retryAfter()` give you what you need to plug in your own policy, for example:

```php
use Boavizta\Api\Exception\BoaviztaException;

function withRetry(callable $call, int $attempts = 3, int $maxDelay = 30): mixed
{
    for ($attempt = 1; ; $attempt++) {
        try {
            return $call();
        } catch (BoaviztaException $e) {
            if (!$e->isRetryable() || $attempt >= $attempts) {
                throw $e;
            }
            sleep(min($e->retryAfter() ?? 2 ** $attempt, $maxDelay));
        }
    }
}

$result = withRetry(fn () => $client->cloud()->instanceImpact('aws', 'a1.4xlarge'));
```

You can also put a retry plugin around your PSR-18 client (e.g. `RetryPlugin` from
`php-http/client-common`). That choice remains yours.

## Development

```bash
composer install
composer test                                        # unit + contract tests, offline
vendor/bin/phpstan analyse src tests bin/record-fixtures --level=8
composer test:live                                   # live tests, against the real server
php examples/quickstart.php
```

### The three test tiers

| Tier | Directory | What it does | What it proves | What it does not prove |
|---|---|---|---|---|
| Unit | `tests/Unit` | A fake HTTP client returns hand-written responses. | The code can read those responses and builds the right requests. | Nothing about the API: the responses are what we believe it sends. |
| Contract | `tests/Contract` | Real responses, recorded on a given day, replayed offline. The replay client also checks that the request sent is the one that was recorded. | The code reads what the API actually sent that day. | That the API still sends the same thing today. |
| Live | `tests/Live` (group `live`) | The same scenarios, sent to the real server, then compared with the recording: HTTP status, then structure, then bytes. | The recording still matches reality. | Nothing at all if it never runs. |

The contract and live tiers share a single list of scenarios
(`tests/Contract/Support/Scenarios.php`): one request per scenario, and checks that must hold on
the recorded response as well as on today's.

Regular continuous integration (`.github/workflows/tests.yml`, on every push to `main` and every
pull request) runs the unit and contract tiers. It never calls the remote server: an outside
contributor never suffers an outage of that server.

The live tier runs every Monday in `.github/workflows/live.yml`, or on demand, with
`bin/verify-live`:

- if it passes, the command updates `tests/Live/last-verified.json` (date, server, API version,
  seal), and the scheduled job pushes that file to `main`. It only runs in
  `DavidJLN/boavizta-api-php`, never in a fork;
- if it fails, the file does not change, and the job opens a `live-api` issue, or comments on the
  one already open. So we hear about it within the week.

`tests/Live/last-verified.json` is produced by `bin/verify-live`, never by hand: its seal is
checked. The `LiveVerificationIsFreshTest` test, in the contract tier that always runs, fails if
the last successful verification is too old: staleness becomes a visible failure, not an
oversight.

#### Staleness threshold: 15 days (`LiveVerificationIsFreshTest::MAX_AGE_DAYS`)

The threshold is computed as **cadence × (tolerated consecutive failures + 1) + margin**, i.e.
7 × (1 + 1) + 1 = 15 days.

- **7-day cadence**: that of the Monday scheduled job.
- **One tolerated failure**: a one-off outage of the remote server, or of GitHub, must not turn a
  whole week of pull requests red, especially those of outside contributors, who have nothing to
  do with it. Maintainers are already warned of that failure by the `live-api` issue.
- **Not two**: after two missed Mondays in a row, the problem is lasting, and every build must say
  so, whoever its author.
- **One day of margin**: GitHub scheduled jobs sometimes start late when the platform is under
  load. That day also leaves time to rerun the job by hand.

Other values were ruled out:

- **8 days** (no tolerated failure) would make everyone pay for a one-day server outage, and would
  duplicate the issue.
- **30 days or more** would only serve to detect a scheduled job that disappeared. The contract
  could stay wrong for a whole month without any build reporting it.

The price of this choice: for two weeks at most, nothing proves the recordings still match the
API. Once there is a release workflow, it will need a stricter threshold, for example 8 days before
creating a tag. Releasing a version that misreads the API costs more than blocking a pull request.

This threshold only makes sense if the live tier is stable. A test that fails at random wears out
the alarm: fix the flakiness (see `unstablePaths` below), do not lengthen the threshold to absorb
it.

A few values are not reproduced by the server from one call to the next, such as the coefficients
of a numerical fit. Each scenario declares them in `unstablePaths`, with the reason in a comment.
The live test checks their type, but not their value. All other values are compared byte for byte.

When the live tier fails while the server is answering, the API has changed.

#### Recordings

Each recording consists of two files in `tests/Contract/fixtures`:

- `<scenario>.body` holds the response body, byte for byte;
- `<scenario>.json` is the adjacent header. It holds the exact URL called (with the request method
  and body), the recording date, the API version the server announced that day, and a SHA-256
  seal over header and body together.

These files are produced by a single command, never by hand:

```bash
bin/record-fixtures                                  # every scenario
bin/record-fixtures component_hdd                    # a single scenario
BOAVIZTA_API_URL=http://localhost:5000 bin/record-fixtures
```

**Editing a recording by hand to make a test pass is forbidden.** A recording that no longer
matches is regenerated with the command, then its diff is read. If the contract tests fail after
that, it is the client that gets fixed.

The seal makes a hand edit detectable: the tests reject any recording whose header or body does
not match its seal. It does not prevent a deliberate falsification, since one could recompute the
seal. The real safeguard remains the live tier: it compares each recording, byte for byte, with
what the server actually answers.

For a local instance: `docker run -p 5000:5000 ghcr.io/boavizta/boaviztapi:latest`.
