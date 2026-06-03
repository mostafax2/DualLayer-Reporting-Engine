<?php

namespace Mostafax\DualLayer\Console\Commands;

use Illuminate\Console\Command;
use Mostafax\DualLayer\Contracts\IdempotencyStoreInterface;
use Mostafax\DualLayer\Domain\SyncOperation\Repositories\SyncOperationRepositoryInterface;
use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\SyncStatus;
use Mostafax\DualLayer\Infrastructure\Jobs\ProcessSyncJob;

class DualReportReprocessCommand extends Command
{
    protected $signature   = 'dual-report:reprocess
                              {--dead    : Reprocess dead-letter (exhausted) operations}
                              {--failed  : Reprocess failed operations (default)}
                              {--limit=100 : Maximum operations to requeue}';
    protected $description = 'Requeue failed or dead-letter sync operations';

    public function handle(
        SyncOperationRepositoryInterface $repo,
        IdempotencyStoreInterface $idempotency,
    ): void {
        $status = $this->option('dead') ? SyncStatus::DEAD : SyncStatus::FAILED;
        $limit  = (int) $this->option('limit');
        $ops    = $repo->findByStatus($status, $limit);

        if (empty($ops)) {
            $this->info("No {$status->value} operations found.");
            return;
        }

        $count = 0;
        foreach ($ops as $op) {
            // Clear idempotency so the retry is actually processed
            $idempotency->forget($op->id()->toString());

            // Reset status to pending so it goes through the engine again
            $repo->updateStatus($op->id(), SyncStatus::PENDING);

            ProcessSyncJob::dispatch($op->model())
                ->onQueue(config('dual-layer.queue.name', 'dual-layer-sync'));

            $count++;
        }

        $this->info("Requeued {$count} {$status->value} operation(s).");
    }
}
