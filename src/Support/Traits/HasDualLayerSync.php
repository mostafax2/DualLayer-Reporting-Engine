<?php

namespace Mostafax\DualLayer\Support\Traits;

/**
 * Optional trait for Eloquent models.
 * Provides getTenantId() hook used by ModelSyncObserver.
 * Add this trait to multi-tenant models.
 */
trait HasDualLayerSync
{
    public function getTenantId(): ?string
    {
        return $this->tenant_id ?? null;
    }
}
