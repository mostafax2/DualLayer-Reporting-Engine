<?php

namespace Mostafax\DualLayer\Infrastructure\Scheduling;

use Mostafax\DualLayer\Contracts\RetrySchedulerInterface;
use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\ModelReference;
use Mostafax\DualLayer\Infrastructure\Jobs\ProcessSyncJob;

final class QueueRetryScheduler implements RetrySchedulerInterface
{
    public function schedule(ModelReference $ref, int $delaySeconds): void
    {
        ProcessSyncJob::dispatch($ref)
            ->onQueue(config('dual-layer.queue.name', 'dual-layer-sync'))
            ->delay(now()->addSeconds($delaySeconds));
    }
}
