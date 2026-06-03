<?php

namespace Mostafax\DualLayer\Contracts;

/**
 * Port for the write-side of the secondary layer (MongoDB, Elasticsearch, etc.)
 */
interface TargetDriverInterface
{
    /**
     * Upsert a document into the target collection.
     *
     * @param  string              $collection
     * @param  string|int          $key          The document's unique key value
     * @param  string              $keyField     The key field name (default: source_id)
     * @param  array<string,mixed> $document
     */
    public function upsert(
        string     $collection,
        string|int $key,
        string     $keyField,
        array      $document,
    ): void;

    /**
     * Remove a document from the target collection.
     */
    public function delete(string $collection, string|int $key, string $keyField): void;

    /**
     * Check whether the driver's backing service is reachable.
     */
    public function ping(): bool;
}
