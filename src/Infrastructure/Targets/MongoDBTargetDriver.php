<?php

namespace Mostafax\DualLayer\Infrastructure\Targets;

use Mostafax\DualLayer\Contracts\TargetDriverInterface;
use Mostafax\DualLayer\Domain\SyncOperation\Exceptions\SyncException;

/**
 * MongoDB target using the mongodb/laravel-mongodb connection.
 * Falls back gracefully when the package is not installed (test environments).
 */
final class MongoDBTargetDriver implements TargetDriverInterface
{
    public function __construct(
        private readonly string $connection = 'mongodb',
    ) {}

    public function upsert(
        string     $collection,
        string|int $key,
        string     $keyField,
        array      $document,
    ): void {
        try {
            $this->db($collection)->updateOne(
                [$keyField => $key],
                ['$set'   => $document],
                ['upsert' => true],
            );
        } catch (\Throwable $e) {
            throw SyncException::targetWriteFailed($collection, $e->getMessage());
        }
    }

    public function delete(string $collection, string|int $key, string $keyField): void
    {
        try {
            $this->db($collection)->deleteOne([$keyField => $key]);
        } catch (\Throwable $e) {
            throw SyncException::targetWriteFailed($collection, $e->getMessage());
        }
    }

    public function ping(): bool
    {
        try {
            \Illuminate\Support\Facades\DB::connection($this->connection)
                ->command(['ping' => 1]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function db(string $collection): \MongoDB\Collection
    {
        /** @var \MongoDB\Laravel\Connection $conn */
        $conn = \Illuminate\Support\Facades\DB::connection($this->connection);
        return $conn->getClient()
            ->selectDatabase($conn->getDatabaseName())
            ->selectCollection($collection);
    }
}
