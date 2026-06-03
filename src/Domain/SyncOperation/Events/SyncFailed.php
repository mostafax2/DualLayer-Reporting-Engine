<?php

namespace Mostafax\DualLayer\Domain\SyncOperation\Events;

use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\ModelReference;

final readonly class SyncFailed
{
    public \DateTimeImmutable $occurredAt;
    public function __construct(
        public readonly string         $syncId,
        public readonly ModelReference $model,
        public readonly string         $error,
        public readonly int            $attempt,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }
}
