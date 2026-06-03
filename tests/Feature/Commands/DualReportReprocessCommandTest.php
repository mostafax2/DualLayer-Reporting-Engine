<?php

use Illuminate\Support\Facades\Queue;
use Mostafax\DualLayer\Infrastructure\Jobs\ProcessSyncJob;
use Mostafax\DualLayer\Infrastructure\Persistence\Eloquent\Models\SyncOperationModel;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(fn () => Queue::fake());

it('requeues failed operations and clears idempotency', function () {
    SyncOperationModel::create([
        'sync_id'     => 'aaa',
        'model_class' => 'App\Models\User',
        'model_id'    => '1',
        'operation'   => 'created',
        'status'      => 'failed',
        'attempts'    => 1,
    ]);

    $this->artisan('dual-report:reprocess', ['--failed' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Requeued 1 failed operation');

    Queue::assertPushed(ProcessSyncJob::class);

    expect(SyncOperationModel::find('aaa')->status)->toBe('pending');
});

it('requeues dead-letter operations', function () {
    SyncOperationModel::create([
        'sync_id'     => 'bbb',
        'model_class' => 'App\Models\User',
        'model_id'    => '2',
        'operation'   => 'updated',
        'status'      => 'dead',
        'attempts'    => 3,
    ]);

    $this->artisan('dual-report:reprocess', ['--dead' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Requeued 1 dead operation');

    Queue::assertPushed(ProcessSyncJob::class);
});

it('reports no operations when queue is empty', function () {
    $this->artisan('dual-report:reprocess', ['--failed' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('No failed operations found');
});
