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
     * The field used as the MongoDB document _id.
     * Defaults to 'source_id' for most cases.
     */
    public function documentKey(): string;
}
