<?php

use Mostafax\DualLayer\Infrastructure\Persistence\Eloquent\Models\SyncOperationModel;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('shows status table with counts for all statuses', function () {
    SyncOperationModel::create([
        'sync_id'     => 'zzz',
        'model_class' => 'App\Models\User',
        'model_id'    => '1',
        'operation'   => 'created',
        'status'      => 'completed',
        'attempts'    => 1,
    ]);

    $this->artisan('dual-report:status')
        ->assertSuccessful()
        ->expectsOutputToContain('DualLayer Reporting Engine');
});

it('warns when dead-letter operations exist', function () {
    SyncOperationModel::create([
        'sync_id'     => 'dead1',
        'model_class' => 'App\Models\User',
        'model_id'    => '5',
        'operation'   => 'updated',
        'status'      => 'dead',
        'attempts'    => 3,
    ]);

    $this->artisan('dual-report:status')
        ->assertSuccessful()
        ->expectsOutputToContain('dead-letter');
});
