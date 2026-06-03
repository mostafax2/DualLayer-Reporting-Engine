<?php

namespace Mostafax\DualLayer\Infrastructure\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mostafax\DualLayer\Application\SyncEngine;
use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\ModelReference;

final class ProcessSyncJob implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue, SerializesModels;

    public int  $tries          = 1;     // SyncEngine owns retry logic
    public int  $timeout        = 60;
    public bool $failOnTimeout  = true;

    public function __construct(public readonly ModelReference $ref) {}

    public function handle(SyncEngine $engine): void
    {
        $engine->process($this->ref);
    }

    public function failed(\Throwable $e): void
    {
        // SyncEngine already marked the operation as failed/dead.
        // This hook is only reached on unexpected exceptions (OOM, timeout).
        \Illuminate\Support\Facades\Log::error('[DualLayer] Job hard-failed', [
            'sync_id' => $this->ref->syncId()->toString(),
            'model'   => $this->ref->modelClass,
            'id'      => $this->ref->modelId,
            'error'   => $e->getMessage(),
        ]);
    }
}
