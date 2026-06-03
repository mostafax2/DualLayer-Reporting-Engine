<?php

namespace Mostafax\DualLayer\Domain\SyncOperation\Events;

use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\ModelReference;

final readonly class SyncRetried
{
    public \DateTimeImmutable $occurredAt;
    public function __construct(
        public readonly string         $syncId,
        public readonly ModelReference $model,
        public readonly int            $attempt,
        public readonly int            $backoffSeconds,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }
}
