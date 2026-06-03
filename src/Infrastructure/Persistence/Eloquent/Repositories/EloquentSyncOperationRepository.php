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
        $ref = new \ReflectionClass(SyncOperation::class);
        $op  = $ref->newInstanceWithoutConstructor();

        $set = function (string $prop, mixed $val) use ($op, $ref): void {
            $p = $ref->getProperty($prop);
            $p->setAccessible(true);
            $p->setValue($op, $val);
        };

        $model = new ModelReference(
            modelClass: $row->model_class,
            modelId:    $row->model_id,
            operation:  $row->operation,
            updatedAt:  $row->model_updated_at?->toISOString() ?? now()->toISOString(),
            tenantId:   $row->tenant_id,
        );

        $set('id',          SyncId::fromString($row->sync_id));
        $set('model',       $model);
        $set('status',      SyncStatus::from($row->status));
        $set('attempts',    $row->attempts);
        $set('maxAttempts', config('dual-layer.retry.max_attempts', 3));
        $set('lastError',   $row->last_error);
        $set('createdAt',   \DateTimeImmutable::createFromInterface($row->created_at));
        $set('domainEvents', []);

        return $op;
    }
}
