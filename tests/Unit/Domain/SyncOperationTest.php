<?php

use Mostafax\DualLayer\Domain\SyncOperation\Entities\SyncOperation;
use Mostafax\DualLayer\Domain\SyncOperation\Events\SyncCompleted;
use Mostafax\DualLayer\Domain\SyncOperation\Events\SyncFailed;
use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\ModelReference;
use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\SyncStatus;

function makeRef(string $op = 'created'): ModelReference
{
    return new ModelReference('App\Models\User', 1, $op, '2024-01-01T00:00:00.000Z');
}

it('initialises with PENDING status and zero attempts', function () {
    $op = SyncOperation::create(makeRef());

    expect($op->status())->toBe(SyncStatus::PENDING)
        ->and($op->attempts())->toBe(0)
        ->and($op->lastError())->toBeNull();
});

it('markProcessing increments attempts and sets PROCESSING', function () {
    $op = SyncOperation::create(makeRef());
    $op->markProcessing();

    expect($op->status())->toBe(SyncStatus::PROCESSING)
        ->and($op->attempts())->toBe(1);
});

it('markCompleted sets COMPLETED and raises SyncCompleted event', function () {
    $op = SyncOperation::create(makeRef());
    $op->markProcessing();
    $op->markCompleted();

    expect($op->status())->toBe(SyncStatus::COMPLETED);

    $events = $op->pullDomainEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(SyncCompleted::class);
});

it('pullDomainEvents clears the event list', function () {
    $op = SyncOperation::create(makeRef());
    $op->markProcessing();
    $op->markCompleted();

    $op->pullDomainEvents();
    expect($op->pullDomainEvents())->toBeEmpty();
});

it('markFailed sets FAILED and raises SyncFailed event when attempts < max', function () {
    $op = SyncOperation::create(makeRef(), maxAttempts: 3);
    $op->markProcessing();
    $op->markFailed('connection timeout');

    expect($op->status())->toBe(SyncStatus::FAILED)
        ->and($op->lastError())->toBe('connection timeout');

    $events = $op->pullDomainEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(SyncFailed::class);
});

it('markFailed sets DEAD (no retry event) when attempts reach max', function () {
    $op = SyncOperation::create(makeRef(), maxAttempts: 1);
    $op->markProcessing();   // attempts = 1
    $op->markFailed('error');

    expect($op->status())->toBe(SyncStatus::DEAD);
    expect($op->pullDomainEvents())->toBeEmpty();
});

it('canRetry returns true for FAILED with remaining attempts', function () {
    $op = SyncOperation::create(makeRef(), maxAttempts: 3);
    $op->markProcessing();
    $op->markFailed('err');

    expect($op->canRetry())->toBeTrue();
});

it('canRetry returns false for DEAD', function () {
    $op = SyncOperation::create(makeRef(), maxAttempts: 1);
    $op->markProcessing();
    $op->markFailed('err');

    expect($op->canRetry())->toBeFalse();
});

it('markSkipped sets SKIPPED', function () {
    $op = SyncOperation::create(makeRef());
    $op->markProcessing();
    $op->markSkipped();

    expect($op->status())->toBe(SyncStatus::SKIPPED);
});

it('backoffSeconds follows exponential schedule', function () {
    $op = SyncOperation::create(makeRef(), maxAttempts: 3);

    $op->markProcessing(); // attempt 1
    expect($op->backoffSeconds())->toBe(30);

    $op->markFailed('err');
    $op->markProcessing(); // attempt 2
    expect($op->backoffSeconds())->toBe(90);

    $op->markFailed('err');
    $op->markProcessing(); // attempt 3
    expect($op->backoffSeconds())->toBe(270);
});

it('reconstitute restores all properties without Reflection', function () {
    $ref = makeRef('updated');
    $id  = $ref->syncId();

    $op = SyncOperation::reconstitute(
        id:          $id,
        model:       $ref,
        status:      SyncStatus::FAILED,
        attempts:    2,
        maxAttempts: 3,
        lastError:   'timeout',
        createdAt:   new \DateTimeImmutable('2024-01-01'),
    );

    expect($op->id()->equals($id))->toBeTrue()
        ->and($op->status())->toBe(SyncStatus::FAILED)
        ->and($op->attempts())->toBe(2)
        ->and($op->lastError())->toBe('timeout')
        ->and($op->canRetry())->toBeTrue();
});
