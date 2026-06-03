<?php

namespace Mostafax\DualLayer\Contracts;

use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\ModelReference;

/**
 * Port for scheduling a delayed retry job.
 * Keeps Application layer free of queue/job Infrastructure imports.
 */
interface RetrySchedulerInterface
{
    public function schedule(ModelReference $ref, int $delaySeconds): void;
}
