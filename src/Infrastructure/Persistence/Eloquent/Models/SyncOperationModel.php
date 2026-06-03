<?php

namespace Mostafax\DualLayer\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent record for tracking every sync operation (audit + reprocess).
 */
class SyncOperationModel extends Model
{
    protected $table      = 'dlr_sync_operations';
    protected $primaryKey = 'sync_id';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'sync_id', 'model_class', 'model_id', 'operation',
        'status', 'attempts', 'last_error', 'tenant_id',
        'model_updated_at',
    ];

    protected $casts = [
        'attempts'         => 'integer',
        'model_updated_at' => 'datetime',
    ];
}
