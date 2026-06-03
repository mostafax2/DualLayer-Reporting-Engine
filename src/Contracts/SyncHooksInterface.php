<?php

namespace Mostafax\DualLayer\Contracts;

/**
 * Optional hooks per model class. Register a class implementing this interface
 * to run logic before/after each sync operation.
 */
interface SyncHooksInterface
{
    public function handles(): string; // model FQCN

    /** Called before the document is written. Return false to abort the sync. */
    public function beforeSync(string $operation, array $document): bool;

    /** Called after a successful write. */
    public function afterSync(string $operation, array $document): void;
}
