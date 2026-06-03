<?php

namespace Mostafax\DualLayer\Contracts;

/**
 * Transforms a MySQL model's attributes into the MongoDB document shape.
 * One implementation per model class.
 */
interface TransformerInterface
{
    /**
     * Return the FQCN of the Eloquent model this transformer handles.
     */
    public function handles(): string;

    /**
     * Return the MongoDB collection name for this model.
     */
    public function collection(): string;

    /**
     * Transform raw Eloquent attributes → MongoDB document array.
     *
     * @param  array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function transform(array $attributes): array;

    /**
     * The field name in the MongoDB document used as the upsert key.
     * Example: 'source_id'
     */
    public function documentKey(): string;

    /**
     * The attribute name in the MySQL model that maps to documentKey().
     * Example: 'id' (when documentKey() = 'source_id')
     * Defaults to 'id' for most cases.
     */
    public function sourceKey(): string;
}
