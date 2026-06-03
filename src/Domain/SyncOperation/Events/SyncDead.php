<?php

namespace Mostafax\DualLayer\Domain\SyncOperation\Events;

use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\ModelReference;

final readonly class SyncDead
{
    public \DateTimeImmutable $occurredAt;

    public function __construct(
        public readonly string         $syncId,
        public readonly ModelReference $model,
        public readonly string         $lastError,
        public readonly int            $totalAttempts,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }
}
