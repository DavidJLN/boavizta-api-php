<?php

declare(strict_types=1);

namespace Boavizta\Api\Response;

/**
 * Impact of one life-cycle phase (embedded or use), with its uncertainty range.
 */
final class PhaseImpact
{
    /**
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly float $value,
        public readonly float $min,
        public readonly float $max,
        public readonly array $warnings = [],
    ) {
    }

    /**
     * `value`, `min` and `max` are required; `warnings` is omitted by the API when there is none.
     *
     * @param array<mixed> $data
     * @param string       $path Dotted path of this phase in the response, for error messages.
     *
     * @throws \Boavizta\Api\Exception\UnexpectedResponseException
     */
    public static function fromArray(array $data, string $path = ''): self
    {
        return new self(
            Payload::float(Payload::get($data, 'value', $path), Payload::join($path, 'value')),
            Payload::float(Payload::get($data, 'min', $path), Payload::join($path, 'min')),
            Payload::float(Payload::get($data, 'max', $path), Payload::join($path, 'max')),
            array_key_exists('warnings', $data) ? Payload::stringList($data['warnings'], Payload::join($path, 'warnings')) : [],
        );
    }
}
