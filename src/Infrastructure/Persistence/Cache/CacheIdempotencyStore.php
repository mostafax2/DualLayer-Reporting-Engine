<?php

namespace Mostafax\DualLayer\Infrastructure\Persistence\Cache;

use Illuminate\Contracts\Cache\Repository as Cache;
use Mostafax\DualLayer\Contracts\IdempotencyStoreInterface;

final class RedisIdempotencyStore implements IdempotencyStoreInterface
{
    private const PREFIX = 'dlr:idempotency:';

    public function __construct(private readonly Cache $cache) {}

    public function wasProcessed(string $syncId): bool
    {
        return $this->cache->has(self::PREFIX . $syncId);
    }

    public function markProcessed(string $syncId, int $ttl = 86400): void
    {
        $this->cache->put(self::PREFIX . $syncId, 1, $ttl);
    }

    public function forget(string $syncId): void
    {
        $this->cache->forget(self::PREFIX . $syncId);
    }
}
