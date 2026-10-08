<?php

declare(strict_types=1);

namespace Boavizta\Api\Response;

use Boavizta\Api\Enum\Criterion;

/**
 * Result of any impact endpoint.
 */
final class ImpactResult
{
    /**
     * @param array<string, CriterionImpact> $impacts  Keyed by criterion name (e.g. "gwp").
     * @param array<string, mixed>|null       $verbose  Raw verbose block, only when requested.
     * @param list<string>                    $warnings Global warnings (e.g. fuzzy-matched cloud instance).
     * @param array<mixed>                    $raw      Full decoded response.
     */
    public function __construct(
        public readonly array $impacts,
        public readonly ?array $verbose,
        public readonly array $warnings,
        public readonly array $raw,
    ) {
    }

    /**
     * `impacts` is required; `warnings` and `verbose` are omitted by the API when there is none.
     *
     * @param array<mixed> $data
     *
     * @throws \Boavizta\Api\Exception\UnexpectedResponseException
     */
    public static function fromArray(array $data): self
    {
        $impacts = [];
        foreach (Payload::object(Payload::get($data, 'impacts', ''), 'impacts') as $criterion => $impact) {
            $path = 'impacts.' . $criterion;
            $impacts[(string) $criterion] = CriterionImpact::fromArray((string) $criterion, Payload::object($impact, $path), $path);
        }

        return new self(
            $impacts,
            array_key_exists('verbose', $data) ? Payload::object($data['verbose'], 'verbose') : null,
            array_key_exists('warnings', $data) ? Payload::stringList($data['warnings'], 'warnings') : [],
            $data,
        );
    }

    public function impact(Criterion|string $criterion): ?CriterionImpact
    {
        return $this->impacts[$criterion instanceof Criterion ? $criterion->value : $criterion] ?? null;
    }

    public function has(Criterion|string $criterion): bool
    {
        return $this->impact($criterion) !== null;
    }
}
