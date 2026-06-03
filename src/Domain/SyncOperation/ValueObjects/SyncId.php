<?php

namespace Mostafax\DualLayer\Domain\SyncOperation\ValueObjects;

/**
 * Deterministic, content-addressable ID — same model+operation+updated_at
 * always produces the same SyncId, which is how we enforce idempotency.
 */
final class SyncId
{
    private function __construct(private readonly string $value) {}

    public static function fromModelState(
        string $modelClass,
        int|string $modelId,
        string $operation,
        string $updatedAt,
    ): self {
        return new self(
            hash('xxh128', implode('|', [$modelClass, $modelId, $operation, $updatedAt]))
        );
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string { return $this->value; }
    public function __toString(): string { return $this->value; }
    public function equals(self $other): bool { return $this->value === $other->value; }
}
