<?php

use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\SyncId;

it('produces the same hash for identical input', function () {
    $a = SyncId::fromModelState('App\Models\User', 1, 'created', '2024-01-01T00:00:00.000Z');
    $b = SyncId::fromModelState('App\Models\User', 1, 'created', '2024-01-01T00:00:00.000Z');

    expect($a->equals($b))->toBeTrue();
    expect($a->toString())->toBe($b->toString());
});

it('produces different hashes when model class differs', function () {
    $a = SyncId::fromModelState('App\Models\User',  1, 'created', '2024-01-01T00:00:00.000Z');
    $b = SyncId::fromModelState('App\Models\Order', 1, 'created', '2024-01-01T00:00:00.000Z');

    expect($a->equals($b))->toBeFalse();
});

it('produces different hashes when operation differs', function () {
    $a = SyncId::fromModelState('App\Models\User', 1, 'created', '2024-01-01T00:00:00.000Z');
    $b = SyncId::fromModelState('App\Models\User', 1, 'updated', '2024-01-01T00:00:00.000Z');

    expect($a->equals($b))->toBeFalse();
});

it('produces different hashes when updatedAt differs', function () {
    $a = SyncId::fromModelState('App\Models\User', 1, 'updated', '2024-01-01T00:00:00.000Z');
    $b = SyncId::fromModelState('App\Models\User', 1, 'updated', '2024-01-02T00:00:00.000Z');

    expect($a->equals($b))->toBeFalse();
});

it('round-trips through fromString', function () {
    $original = SyncId::fromModelState('App\Models\User', 42, 'deleted', '2024-06-01T12:00:00.000Z');
    $restored = SyncId::fromString($original->toString());

    expect($restored->equals($original))->toBeTrue();
});

it('casts to string via __toString', function () {
    $id = SyncId::fromModelState('App\Models\User', 1, 'created', '2024-01-01T00:00:00.000Z');

    expect((string) $id)->toBe($id->toString());
});
