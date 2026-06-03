<?php

namespace Mostafax\DualLayer\Contracts;

/**
 * Tracks which sync_ids have already been processed.
 * Redis implementation is default; any key-value store works.
 */
interface IdempotencyStoreInterface
{
    /**
     * Returns true if this syncId has already been processed.
     */
    public function wasProcessed(string $syncId): bool;

    /**
     * Mark a syncId as processed, with optional TTL (seconds).
     */
    public function markProcessed(string $syncId, int $ttl = 86400): void;

    /**
     * Remove a syncId from the store (used when reprocessing dead letters).
     */
    public function forget(string $syncId): void;
}
