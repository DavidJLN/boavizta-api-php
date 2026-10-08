<?php

declare(strict_types=1);

namespace Boavizta\Api\Tests\Contract\Support;

/**
 * Last successful run of the live tests against a real server, as stored in tests/Live/last-verified.json.
 *
 * Versioned, written by bin/verify-live and never by hand: the contract tests, which always run,
 * fail when it gets too old, so a live tier that silently stopped running turns into a red build.
 */
final class LiveVerification
{
    public const FILE = __DIR__ . '/../../Live/last-verified.json';
    public const WRITER = 'bin/verify-live';
    private const JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public function __construct(
        public readonly \DateTimeImmutable $verifiedAt,
        public readonly string $baseUri,
        public readonly string $apiVersion,
    ) {
    }

    /**
     * @throws \UnexpectedValueException when the file is missing or was not written as-is by bin/verify-live
     */
    public static function load(): self
    {
        if (!is_file(self::FILE)) {
            throw new \UnexpectedValueException(sprintf('%s is missing: run %s', self::FILE, self::WRITER));
        }
        $data = json_decode((string) file_get_contents(self::FILE), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \UnexpectedValueException(self::FILE . ' is not a JSON object');
        }
        $seal = $data['sha256'] ?? '';
        unset($data['sha256']);
        if (($data['verified_by'] ?? null) !== self::WRITER || !is_string($seal) || !hash_equals(self::seal($data), $seal)) {
            throw new \UnexpectedValueException(sprintf(
                '%s does not match the seal written by %s: it was edited by hand. Never do that; run %s against the real server.',
                self::FILE,
                self::WRITER,
                self::WRITER,
            ));
        }

        return new self(new \DateTimeImmutable($data['verified_at']), $data['base_uri'], $data['api_version']);
    }

    public function save(): void
    {
        $data = [
            'verified_by' => self::WRITER,
            'verified_at' => $this->verifiedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'base_uri' => $this->baseUri,
            'api_version' => $this->apiVersion,
        ];
        $data['sha256'] = self::seal($data);
        file_put_contents(self::FILE, json_encode($data, self::JSON_FLAGS | JSON_PRETTY_PRINT) . "\n");
    }

    /**
     * @param array<mixed> $data
     */
    private static function seal(array $data): string
    {
        return hash('sha256', json_encode($data, self::JSON_FLAGS));
    }
}
