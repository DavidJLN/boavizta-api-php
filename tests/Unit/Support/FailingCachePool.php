<?php

declare(strict_types=1);

namespace Boavizta\Api\Tests\Unit\Support;

use Psr\Cache\CacheException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * A PSR-6 pool whose backend is down: every operation throws.
 */
final class FailingCachePool implements CacheItemPoolInterface
{
    public function getItem(string $key): CacheItemInterface
    {
        throw self::down();
    }

    /**
     * @param string[] $keys
     *
     * @return iterable<string, CacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        throw self::down();
    }

    public function hasItem(string $key): bool
    {
        throw self::down();
    }

    public function clear(): bool
    {
        throw self::down();
    }

    public function deleteItem(string $key): bool
    {
        throw self::down();
    }

    /**
     * @param string[] $keys
     */
    public function deleteItems(array $keys): bool
    {
        throw self::down();
    }

    public function save(CacheItemInterface $item): bool
    {
        throw self::down();
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        throw self::down();
    }

    public function commit(): bool
    {
        throw self::down();
    }

    private static function down(): CacheException
    {
        return new class('cache backend unreachable') extends \RuntimeException implements CacheException {
        };
    }
}
