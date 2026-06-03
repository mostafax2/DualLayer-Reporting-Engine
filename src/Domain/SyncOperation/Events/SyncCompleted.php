<?php

namespace Mostafax\DualLayer\Domain\SyncOperation\Events;

use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\ModelReference;

final readonly class SyncCompleted
{
    public \DateTimeImmutable $occurredAt;
    public function __construct(
        public readonly string         $syncId,
        public readonly ModelReference $model,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }
}
