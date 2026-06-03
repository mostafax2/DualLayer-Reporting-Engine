<?php

namespace Mostafax\DualLayer\Infrastructure\Persistence\Eloquent\Repositories;

use Mostafax\DualLayer\Domain\SyncOperation\Entities\SyncOperation;
use Mostafax\DualLayer\Domain\SyncOperation\Repositories\SyncOperationRepositoryInterface;
use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\ModelReference;
use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\SyncId;
use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\SyncStatus;
use Mostafax\DualLayer\Infrastructure\Persistence\Eloquent\Models\SyncOperationModel;

final class EloquentSyncOperationRepository implements SyncOperationRepositoryInterface
{
    public function save(SyncOperation $op): void
    {
        SyncOperationModel::updateOrCreate(
            ['sync_id' => $op->id()->toString()],
            [
                'model_class'       => $op->model()->modelClass,
                'model_id'          => $op->model()->modelId,
                'operation'         => $op->model()->operation,
                'tenant_id'         => $op->model()->tenantId,
                'model_updated_at'  => $op->model()->updatedAt,
                'status'            => $op->status()->value,
                'attempts'          => $op->attempts(),
                'last_error'        => $op->lastError(),
            ]
        );
    }

    public function findById(SyncId $id): ?SyncOperation
    {
        $row = SyncOperationModel::find($id->toString());
        return $row ? $this->hydrate($row) : null;
    }

    public function countByStatus(SyncStatus $status): int
    {
        return SyncOperationModel::where('status', $status->value)->count();
    }

    public function findByStatus(SyncStatus $status, int $limit = 100): array
    {
        return SyncOperationModel::where('status', $status->value)
            ->limit($limit)
            ->get()
            ->map(fn($row) => $this->hydrate($row))
            ->all();
    }

    public function updateStatus(SyncId $id, SyncStatus $status, ?string $error = null): void
    {
        SyncOperationModel::where('sync_id', $id->toString())
            ->update(array_filter([
                'status'     => $status->value,
                'last_error' => $error,
            ], fn($v) => $v !== null));
    }

    private function hydrate(SyncOperationModel $row): SyncOperation
    {
        return SyncOperation::reconstitute(
            id:          SyncId::fromString($row->sync_id),
            model:       new ModelReference(
                modelClass: $row->model_class,
                modelId:    $row->model_id,
                operation:  $row->operation,
                updatedAt:  $row->model_updated_at?->toISOString() ?? now()->toISOString(),
                tenantId:   $row->tenant_id,
            ),
            status:      SyncStatus::from($row->status),
            attempts:    $row->attempts,
            maxAttempts: config('dual-layer.retry.max_attempts', 3),
            lastError:   $row->last_error,
            createdAt:   \DateTimeImmutable::createFromInterface($row->created_at),
        );
    }
}
