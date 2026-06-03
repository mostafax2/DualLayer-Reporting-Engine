<?php

namespace Mostafax\DualLayer\Domain\SyncOperation\Exceptions;

class SyncException extends \RuntimeException
{
    public static function transformerNotFound(string $modelClass): self
    {
        return new self("No transformer registered for [{$modelClass}].");
    }

    public static function targetWriteFailed(string $collection, string $reason): self
    {
        return new self("Failed writing to target collection [{$collection}]: {$reason}");
    }

    public static function sourceNotFound(string $modelClass, int|string $id): self
    {
        return new self("Source record not found: [{$modelClass}#{$id}]. May have been deleted.");
    }
}
