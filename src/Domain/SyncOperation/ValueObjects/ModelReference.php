<?php

namespace Mostafax\DualLayer\Domain\SyncOperation\ValueObjects;

final class ModelReference
{
    public function __construct(
        public readonly string     $modelClass,
        public readonly int|string $modelId,
        public readonly string     $operation,   // created | updated | deleted
        public readonly string     $updatedAt,   // ISO-8601 from model's updated_at
        public readonly ?string    $tenantId = null,
    ) {}

    public function syncId(): SyncId
    {
        return SyncId::fromModelState(
            $this->modelClass,
            $this->modelId,
            $this->operation,
            $this->updatedAt,
        );
    }

    public function toArray(): array
    {
        return [
            'model_class' => $this->modelClass,
            'model_id'    => $this->modelId,
            'operation'   => $this->operation,
            'updated_at'  => $this->updatedAt,
            'tenant_id'   => $this->tenantId,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            modelClass: $data['model_class'],
            modelId:    $data['model_id'],
            operation:  $data['operation'],
            updatedAt:  $data['updated_at'],
            tenantId:   $data['tenant_id'] ?? null,
        );
    }
}
