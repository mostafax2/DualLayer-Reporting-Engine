<?php

namespace Mostafax\DualLayer\Infrastructure\Observers;

use Illuminate\Database\Eloquent\Model;
use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\ModelReference;
use Mostafax\DualLayer\Infrastructure\Jobs\ProcessSyncJob;

/**
 * Registered dynamically on each observed model class.
 * Captures created/updated/deleted events and enqueues a sync job.
 */
final class ModelSyncObserver
{
    public function created(Model $model): void
    {
        $this->enqueue($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->enqueue($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->enqueue($model, 'deleted');
    }

    // Soft-delete treated as update (the deleted_at column changes)
    public function restored(Model $model): void
    {
        $this->enqueue($model, 'updated');
    }

    private function enqueue(Model $model, string $operation): void
    {
        $tenantId = method_exists($model, 'getTenantId')
            ? $model->getTenantId()
            : ($model->tenant_id ?? null);

        $ref = new ModelReference(
            modelClass: get_class($model),
            modelId:    $model->getKey(),
            operation:  $operation,
            updatedAt:  ($model->updated_at ?? $model->created_at ?? now())->toISOString(),
            tenantId:   $tenantId,
        );

        ProcessSyncJob::dispatch($ref)
            ->onQueue(config('dual-layer.queue.name', 'dual-layer-sync'));
    }
}
