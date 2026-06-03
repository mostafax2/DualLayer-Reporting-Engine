<?php

namespace Mostafax\DualLayer\Infrastructure\Targets;

use Mostafax\DualLayer\Contracts\TargetDriverInterface;

/**
 * No-op target — useful for testing or disabling sync without changing code.
 */
final class NullTargetDriver implements TargetDriverInterface
{
    public function upsert(string $collection, string|int $key, string $keyField, array $document): void {}
    public function delete(string $collection, string|int $key, string $keyField): void {}
    public function ping(): bool { return true; }
}
