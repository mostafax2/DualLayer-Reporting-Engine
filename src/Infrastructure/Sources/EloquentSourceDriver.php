<?php

namespace Mostafax\DualLayer\Infrastructure\Sources;

use Mostafax\DualLayer\Contracts\SourceDriverInterface;

final class EloquentSourceDriver implements SourceDriverInterface
{
    public function fetch(string $modelClass, int|string $modelId): ?array
    {
        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = $modelClass::withTrashed()->find($modelId);

        return $model?->attributesToArray();
    }
}
