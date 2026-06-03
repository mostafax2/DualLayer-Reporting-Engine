<?php

use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\ModelReference;

it('round-trips through toArray and fromArray', function () {
    $ref = new ModelReference(
        modelClass: 'App\Models\Order',
        modelId:    99,
        operation:  'updated',
        updatedAt:  '2024-03-15T10:00:00.000Z',
        tenantId:   'tenant-abc',
    );

    $restored = ModelReference::fromArray($ref->toArray());

    expect($restored->modelClass)->toBe($ref->modelClass)
        ->and($restored->modelId)->toBe($ref->modelId)
        ->and($restored->operation)->toBe($ref->operation)
        ->and($restored->updatedAt)->toBe($ref->updatedAt)
        ->and($restored->tenantId)->toBe($ref->tenantId);
});

it('generates the same syncId for the same state', function () {
    $ref1 = new ModelReference('App\Models\User', 1, 'created', '2024-01-01T00:00:00.000Z');
    $ref2 = new ModelReference('App\Models\User', 1, 'created', '2024-01-01T00:00:00.000Z');

    expect($ref1->syncId()->equals($ref2->syncId()))->toBeTrue();
});

it('handles null tenantId', function () {
    $ref = new ModelReference('App\Models\User', 1, 'created', '2024-01-01T00:00:00.000Z');

    expect($ref->tenantId)->toBeNull();
    expect($ref->toArray()['tenant_id'])->toBeNull();
});
