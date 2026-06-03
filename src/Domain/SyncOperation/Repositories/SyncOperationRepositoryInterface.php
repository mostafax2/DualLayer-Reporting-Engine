<?php

namespace Mostafax\DualLayer\Domain\SyncOperation\Repositories;

use Mostafax\DualLayer\Domain\SyncOperation\Entities\SyncOperation;
use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\SyncId;
use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\SyncStatus;

interface SyncOperationRepositoryInterface
{
    public function save(SyncOperation $operation): void;

    public function findById(SyncId $id): ?SyncOperation;

    public function countByStatus(SyncStatus $status): int;

    /** @return SyncOperation[] */
    public function findByStatus(SyncStatus $status, int $limit = 100): array;

    public function updateStatus(SyncId $id, SyncStatus $status, ?string $error = null): void;
}
