<?php

declare(strict_types=1);

namespace Boavizta\Api\Tests\Contract\Support;

use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

/**
 * One HTTP request and the response the real API gave to it.
 *
 * On disk a recording is two files, both written by bin/record-fixtures and never by hand:
 * - `<name>.body`: the response body, byte for byte as the API sent it;
 * - `<name>.json`: its header: exact URL, method and request body, date of the recording,
 *   version announced by the server that day, and a SHA-256 sealing the header and the body
 *   together, so that a hand edit of either file is detected.
 *
 * A recording that no longer matches is regenerated, then its diff is read.
 */
final class Exchange
{
    public const FIXTURES_DIR = __DIR__ . '/../fixtures';
    public const RECORDER = 'bin/record-fixtures';
    private const JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;

    /**
     * @param mixed $requestBody Decoded JSON body of the request, null for a GET.
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly string $path,
        public readonly string $query,
        public readonly mixed $requestBody,
        public readonly int $status,
        public readonly string $contentType,
        public readonly string $responseBody,
        public readonly ?string $recordedAt = null,
        public readonly ?string $apiVersion = null,
    ) {
    }

    public static function fromPsr(RequestInterface $request, string $basePath, int $status, string $contentType, string $responseBody): self
    {
        [$method, $path, $query, $body] = self::describe($request, $basePath);

        return new self($method, (string) $request->getUri(), $path, $query, $body, $status, $contentType, $responseBody);
    }

    /**
     * @return array{string, string, string, mixed} method, path relative to the base URI, query string, decoded body
     */
    public static function describe(RequestInterface $request, string $basePath): array
    {
        $path = $request->getUri()->getPath();
        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }
        $raw = (string) $request->getBody();
        $request->getBody()->rewind();

        return [
            $request->getMethod(),
            $path,
            $request->getUri()->getQuery(),
            $raw === '' ? null : json_decode($raw, true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * Fails the current test when `$request` is not the request that was recorded.
     */
    public function assertSameRequest(RequestInterface $request, string $basePath): void
    {
        [$method, $path, $query, $body] = self::describe($request, $basePath);

        Assert::assertSame(
            [$this->method, $this->path, $this->query, $this->requestBody],
            [$method, $path, $query, $body],
            sprintf('The client no longer sends the request that was recorded; if the change is intended, regenerate the recording with %s and read the diff.', self::RECORDER),
        );
    }

    /**
     * Decoded response body, or null when the API did not answer with JSON (e.g. a bare "Internal Server Error").
     */
    public function decodedResponse(): mixed
    {
        try {
            return json_decode($this->responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }

    public static function exists(string $name): bool
    {
        return is_file(self::FIXTURES_DIR . "/$name.json") && is_file(self::FIXTURES_DIR . "/$name.body");
    }

    /**
     * @throws \UnexpectedValueException when the recording was not produced as-is by the recorder
     */
    public static function load(string $name): self
    {
        $header = json_decode((string) file_get_contents(self::FIXTURES_DIR . "/$name.json"), true, 512, JSON_THROW_ON_ERROR);
        $body = file_get_contents(self::FIXTURES_DIR . "/$name.body");
        if (!is_array($header) || $body === false) {
            throw new \UnexpectedValueException("Unreadable recording $name");
        }
        if (($header['recorded_by'] ?? null) !== self::RECORDER || !is_string($header['recorded_at'] ?? null) || !is_string($header['api_version'] ?? null)) {
            throw new \UnexpectedValueException(sprintf('Recording %s has no recorder, date or API version in its header: regenerate it with %s', $name, self::RECORDER));
        }
        $seal = $header['sha256'] ?? '';
        unset($header['sha256']);
        if (!is_string($seal) || !hash_equals(self::seal($header, $body), $seal)) {
            throw new \UnexpectedValueException(sprintf(
                'Recording %s does not match the seal written by the recorder: it was edited after recording. Never edit a recording by hand; regenerate it with %s %s and read the diff.',
                $name,
                self::RECORDER,
                $name,
            ));
        }

        return new self(
            $header['request']['method'],
            $header['request']['url'],
            $header['request']['path'],
            $header['request']['query'],
            $header['request']['body'],
            $header['response']['status'],
            $header['response']['content_type'],
            $body,
            $header['recorded_at'],
            $header['api_version'],
        );
    }

    public function save(string $name, string $recordedAt, string $apiVersion): void
    {
        $header = [
            'recorded_by' => self::RECORDER,
            'recorded_at' => $recordedAt,
            'api_version' => $apiVersion,
            'request' => [
                'method' => $this->method,
                'url' => $this->url,
                'path' => $this->path,
                'query' => $this->query,
                'body' => $this->requestBody,
            ],
            'response' => [
                'status' => $this->status,
                'content_type' => $this->contentType,
            ],
        ];
        $header['sha256'] = self::seal($header, $this->responseBody);
        file_put_contents(self::FIXTURES_DIR . "/$name.body", $this->responseBody);
        file_put_contents(self::FIXTURES_DIR . "/$name.json", json_encode($header, self::JSON_FLAGS | JSON_PRETTY_PRINT) . "\n");
    }

    /**
     * @param array<mixed> $header
     */
    private static function seal(array $header, string $body): string
    {
        return hash('sha256', json_encode($header, self::JSON_FLAGS) . "\0" . $body);
    }
}
