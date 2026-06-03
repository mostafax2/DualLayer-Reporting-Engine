<?php

namespace Mostafax\DualLayer\Domain\SyncOperation\ValueObjects;

enum SyncStatus: string
{
    case PENDING    = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED  = 'completed';
    case FAILED     = 'failed';
    case DEAD       = 'dead';       // exhausted all retries
    case SKIPPED    = 'skipped';    // idempotency hit

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::DEAD, self::SKIPPED]);
    }

    public function canRetry(): bool
    {
        return $this === self::FAILED;
    }
}
