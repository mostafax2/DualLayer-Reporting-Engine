<?php

namespace Mostafax\DualLayer\Contracts;

/**
 * Port for reading from the primary source (MySQL via Eloquent).
 * Abstracted so the sync job never imports Eloquent directly.
 */
interface SourceDriverInterface
{
    /**
     * Fetch fresh attributes of a model record.
     *
     * @return array<string,mixed>|null  null when record has been hard-deleted
     */
    public function fetch(string $modelClass, int|string $modelId): ?array;
}
