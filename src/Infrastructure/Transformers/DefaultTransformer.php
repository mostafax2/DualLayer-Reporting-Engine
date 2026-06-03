<?php

namespace Mostafax\DualLayer\Infrastructure\Transformers;

use Mostafax\DualLayer\Contracts\TransformerInterface;

/**
 * Pass-through transformer: stores the raw Eloquent attributes as-is.
 * Wraps them in a standard envelope with sync metadata.
 * Used when no custom transformer is registered for a model.
 */
final class DefaultTransformer implements TransformerInterface
{
    public function __construct(private readonly string $modelClass) {}

    public function handles(): string
    {
        return $this->modelClass;
    }

    public function collection(): string
    {
        // e.g. App\Models\User → users
        return \Illuminate\Support\Str::plural(
            \Illuminate\Support\Str::snake(class_basename($this->modelClass))
        );
    }

    public function transform(array $attributes): array
    {
        return [
            'source_type' => \Illuminate\Support\Str::snake(class_basename($this->modelClass)),
            'source_id'   => $attributes['id'] ?? null,
            'data'        => $attributes,
            'synced_at'   => now()->toISOString(),
        ];
    }

    public function documentKey(): string
    {
        return 'source_id';
    }

    public function sourceKey(): string
    {
        return 'id';
    }
}
