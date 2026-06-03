<?php

use Mostafax\DualLayer\Application\SyncEngine;
use Mostafax\DualLayer\Contracts\IdempotencyStoreInterface;
use Mostafax\DualLayer\Contracts\RetrySchedulerInterface;
use Mostafax\DualLayer\Contracts\SourceDriverInterface;
use Mostafax\DualLayer\Contracts\SyncHooksInterface;
use Mostafax\DualLayer\Contracts\TargetDriverInterface;
use Mostafax\DualLayer\Domain\SyncOperation\Entities\SyncOperation;
use Mostafax\DualLayer\Domain\SyncOperation\Repositories\SyncOperationRepositoryInterface;
use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\ModelReference;
use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\SyncStatus;

// ── helpers ────────────────────────────────────────────────────────────────

function makeEngineRef(string $op = 'created'): ModelReference
{
    return new ModelReference('App\Models\User', 1, $op, '2024-01-01T00:00:00.000Z');
}

function buildEngine(
    SourceDriverInterface            $source,
    TargetDriverInterface            $target,
    IdempotencyStoreInterface        $idempotency,
    SyncOperationRepositoryInterface $repo,
    RetrySchedulerInterface          $scheduler,
): SyncEngine {
    return new SyncEngine($source, $target, $idempotency, $repo, $scheduler);
}

// ── tests ──────────────────────────────────────────────────────────────────

it('upserts a document on created operation', function () {
    $source      = mock(SourceDriverInterface::class);
    $target      = mock(TargetDriverInterface::class);
    $idempotency = mock(IdempotencyStoreInterface::class);
    $repo        = mock(SyncOperationRepositoryInterface::class);
    $scheduler   = mock(RetrySchedulerInterface::class);

    $source->allows('fetch')->andReturn(['id' => 1, 'name' => 'Alice']);
    $idempotency->allows('wasProcessed')->andReturn(false);
    $idempotency->expects('markProcessed')->once();
    $repo->allows('findById')->andReturn(null);
    $repo->allows('save');
    $target->expects('upsert')->once();

    buildEngine($source, $target, $idempotency, $repo, $scheduler)
        ->process(makeEngineRef('created'));
});

it('calls target delete on deleted operation without fetching source', function () {
    $source      = mock(SourceDriverInterface::class);
    $target      = mock(TargetDriverInterface::class);
    $idempotency = mock(IdempotencyStoreInterface::class);
    $repo        = mock(SyncOperationRepositoryInterface::class);
    $scheduler   = mock(RetrySchedulerInterface::class);

    $idempotency->allows('wasProcessed')->andReturn(false);
    $idempotency->allows('markProcessed');
    $repo->allows('findById')->andReturn(null);
    $repo->allows('save');

    $source->expects('fetch')->never();
    $target->expects('delete')->once();

    buildEngine($source, $target, $idempotency, $repo, $scheduler)
        ->process(makeEngineRef('deleted'));
});

it('silently skips when idempotency store says already processed', function () {
    $source      = mock(SourceDriverInterface::class);
    $target      = mock(TargetDriverInterface::class);
    $idempotency = mock(IdempotencyStoreInterface::class);
    $repo        = mock(SyncOperationRepositoryInterface::class);
    $scheduler   = mock(RetrySchedulerInterface::class);

    $idempotency->allows('wasProcessed')->andReturn(true);

    $source->expects('fetch')->never();
    $target->expects('upsert')->never();
    $repo->expects('save')->never();

    buildEngine($source, $target, $idempotency, $repo, $scheduler)
        ->process(makeEngineRef());
});

it('marks operation SKIPPED when a hook returns false', function () {
    $source      = mock(SourceDriverInterface::class);
    $target      = mock(TargetDriverInterface::class);
    $idempotency = mock(IdempotencyStoreInterface::class);
    $repo        = mock(SyncOperationRepositoryInterface::class);
    $scheduler   = mock(RetrySchedulerInterface::class);

    $source->allows('fetch')->andReturn(['id' => 1, 'name' => 'Alice']);
    $idempotency->allows('wasProcessed')->andReturn(false);
    $repo->allows('findById')->andReturn(null);

    $savedStatuses = [];
    $repo->allows('save')->andReturnUsing(function (SyncOperation $op) use (&$savedStatuses) {
        $savedStatuses[] = $op->status();
    });

    $target->expects('upsert')->never();

    $hook = new class implements SyncHooksInterface {
        public function handles(): string                              { return 'App\Models\User'; }
        public function beforeSync(string $op, array $doc): bool      { return false; }
        public function afterSync(string $op, array $doc): void       {}
    };

    $engine = buildEngine($source, $target, $idempotency, $repo, $scheduler);
    $engine->registerHooks($hook);
    $engine->process(makeEngineRef());

    expect(last($savedStatuses))->toBe(SyncStatus::SKIPPED);
});

it('schedules a retry when an operation fails and has remaining attempts', function () {
    $source      = mock(SourceDriverInterface::class);
    $target      = mock(TargetDriverInterface::class);
    $idempotency = mock(IdempotencyStoreInterface::class);
    $repo        = mock(SyncOperationRepositoryInterface::class);
    $scheduler   = mock(RetrySchedulerInterface::class);

    $source->allows('fetch')->andReturn(['id' => 1]);
    $idempotency->allows('wasProcessed')->andReturn(false);
    $repo->allows('findById')->andReturn(null);
    $repo->allows('save');

    $target->allows('upsert')->andThrow(new \RuntimeException('MongoDB down'));

    $scheduler->expects('schedule')->once();

    buildEngine($source, $target, $idempotency, $repo, $scheduler)
        ->process(makeEngineRef());
});

it('does NOT schedule retry when all attempts are exhausted (dead letter)', function () {
    $source      = mock(SourceDriverInterface::class);
    $target      = mock(TargetDriverInterface::class);
    $idempotency = mock(IdempotencyStoreInterface::class);
    $repo        = mock(SyncOperationRepositoryInterface::class);
    $scheduler   = mock(RetrySchedulerInterface::class);

    $source->allows('fetch')->andReturn(['id' => 1]);
    $idempotency->allows('wasProcessed')->andReturn(false);
    $target->allows('upsert')->andThrow(new \RuntimeException('err'));
    $scheduler->expects('schedule')->never();

    // Provide an existing op that has already consumed all attempts
    $exhaustedOp = SyncOperation::reconstitute(
        id:          makeEngineRef()->syncId(),
        model:       makeEngineRef(),
        status:      \Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\SyncStatus::FAILED,
        attempts:    3,
        maxAttempts: 3,
        lastError:   'previous error',
        createdAt:   new \DateTimeImmutable(),
    );
    $repo->allows('findById')->andReturn($exhaustedOp);
    $repo->allows('save');

    buildEngine($source, $target, $idempotency, $repo, $scheduler)
        ->process(makeEngineRef());
});

it('deletes from target when source returns null for a non-delete operation', function () {
    $source      = mock(SourceDriverInterface::class);
    $target      = mock(TargetDriverInterface::class);
    $idempotency = mock(IdempotencyStoreInterface::class);
    $repo        = mock(SyncOperationRepositoryInterface::class);
    $scheduler   = mock(RetrySchedulerInterface::class);

    $source->allows('fetch')->andReturn(null); // hard deleted between observer and job
    $idempotency->allows('wasProcessed')->andReturn(false);
    $idempotency->allows('markProcessed');
    $repo->allows('findById')->andReturn(null);
    $repo->allows('save');

    $target->expects('upsert')->never();
    $target->expects('delete')->once();

    buildEngine($source, $target, $idempotency, $repo, $scheduler)
        ->process(makeEngineRef('updated'));
});
