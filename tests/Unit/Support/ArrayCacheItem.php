<?php

declare(strict_types=1);

namespace Boavizta\Api\Tests\Unit\Support;

use Psr\Cache\CacheItemInterface;

final class ArrayCacheItem implements CacheItemInterface
{
    public bool $hit = false;
    public ?int $ttl = null;
    private mixed $value = null;

    public function __construct(private readonly string $key)
    {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        $this->ttl = $time instanceof \DateInterval ? null : $time;

        return $this;
    }
}
