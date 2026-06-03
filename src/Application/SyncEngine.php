<?php

namespace Mostafax\DualLayer\Application;

use Illuminate\Support\Facades\Log;
use Mostafax\DualLayer\Contracts\IdempotencyStoreInterface;
use Mostafax\DualLayer\Contracts\SourceDriverInterface;
use Mostafax\DualLayer\Contracts\SyncHooksInterface;
use Mostafax\DualLayer\Contracts\TargetDriverInterface;
use Mostafax\DualLayer\Contracts\TransformerInterface;
use Mostafax\DualLayer\Domain\SyncOperation\Entities\SyncOperation;
use Mostafax\DualLayer\Domain\SyncOperation\Exceptions\SyncException;
use Mostafax\DualLayer\Domain\SyncOperation\Repositories\SyncOperationRepositoryInterface;
use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\ModelReference;
use Mostafax\DualLayer\Infrastructure\Jobs\ProcessSyncJob;
use Mostafax\DualLayer\Infrastructure\Transformers\DefaultTransformer;

/**
 * Central orchestrator. Called by ProcessSyncJob.
 * Owns: idempotency check, transform, write, retry scheduling, audit.
 */
final class SyncEngine
{
    /** @var array<string, TransformerInterface> model FQCN → transformer */
    private array $transformers = [];

    /** @var array<string, SyncHooksInterface> model FQCN → hooks */
    private array $hooks = [];

    public function __construct(
        private readonly SourceDriverInterface             $source,
        private readonly TargetDriverInterface             $target,
        private readonly IdempotencyStoreInterface         $idempotency,
        private readonly SyncOperationRepositoryInterface  $repository,
    ) {}

    // ── Registration (called from DualLayerManager) ────────────────────────

    public function registerTransformer(TransformerInterface $transformer): void
    {
        $this->transformers[$transformer->handles()] = $transformer;
    }

    public function registerHooks(SyncHooksInterface $hooks): void
    {
        $this->hooks[$hooks->handles()] = $hooks;
    }

    // ── Core process ───────────────────────────────────────────────────────

    public function process(ModelReference $ref): void
    {
        $syncId = $ref->syncId()->toString();

        // 1. Idempotency check — skip if already processed
        if ($this->idempotency->wasProcessed($syncId)) {
            Log::debug("[DualLayer] Skipped (idempotent): {$syncId}");
            return;
        }

        // 2. Load or create the operation record
        $op = $this->repository->findById($ref->syncId())
            ?? SyncOperation::create($ref, config('dual-layer.retry.max_attempts', 3));

        $op->markProcessing();
        $this->repository->save($op);

        try {
            $this->execute($ref, $op);

            // 3. Mark processed in idempotency store first (prevents re-entry on crash)
            $this->idempotency->markProcessed($syncId, config('dual-layer.idempotency.ttl', 86400));

            $op->markCompleted();
            $this->repository->save($op);

            foreach ($op->pullDomainEvents() as $event) {
                event($event);
            }

            Log::info("[DualLayer] Synced: {$ref->modelClass}#{$ref->modelId} op={$ref->operation}");

        } catch (\Throwable $e) {
            $op->markFailed($e->getMessage());
            $this->repository->save($op);

            foreach ($op->pullDomainEvents() as $event) {
                event($event);
            }

            Log::error("[DualLayer] Failed: {$syncId}", [
                'model'   => $ref->modelClass,
                'id'      => $ref->modelId,
                'attempt' => $op->attempts(),
                'error'   => $e->getMessage(),
            ]);

            if ($op->canRetry()) {
                $this->scheduleRetry($ref, $op->backoffSeconds());
            } else {
                Log::warning("[DualLayer] Dead letter: {$syncId} (exhausted {$op->attempts()} attempts)");
            }
        }
    }

    // ── Internals ──────────────────────────────────────────────────────────

    private function execute(ModelReference $ref, SyncOperation $op): void
    {
        $transformer = $this->resolveTransformer($ref->modelClass);

        if ($ref->operation === 'deleted') {
            $this->target->delete(
                $transformer->collection(),
                $ref->modelId,
                $transformer->documentKey(),
            );
            return;
        }

        // Fetch fresh attributes from source (re-read to avoid stale observer data)
        $attributes = $this->source->fetch($ref->modelClass, $ref->modelId);

        // Deleted between observer fire and job execution — treat as delete
        if ($attributes === null) {
            $this->target->delete(
                $transformer->collection(),
                $ref->modelId,
                $transformer->documentKey(),
            );
            return;
        }

        $document = $transformer->transform($attributes);

        // Before-sync hook (can abort)
        if (isset($this->hooks[$ref->modelClass])) {
            $proceed = $this->hooks[$ref->modelClass]->beforeSync($ref->operation, $document);
            if (! $proceed) {
                Log::debug("[DualLayer] Aborted by hook: {$ref->modelClass}#{$ref->modelId}");
                return;
            }
        }

        $this->target->upsert(
            $transformer->collection(),
            $attributes[$transformer->documentKey() === 'source_id' ? 'id' : $transformer->documentKey()],
            $transformer->documentKey(),
            $document,
        );

        // After-sync hook
        $this->hooks[$ref->modelClass]?->afterSync($ref->operation, $document);
    }

    private function resolveTransformer(string $modelClass): TransformerInterface
    {
        return $this->transformers[$modelClass] ?? new DefaultTransformer($modelClass);
    }

    private function scheduleRetry(ModelReference $ref, int $backoffSeconds): void
    {
        ProcessSyncJob::dispatch($ref)
            ->onQueue(config('dual-layer.queue.name', 'dual-layer-sync'))
            ->delay(now()->addSeconds($backoffSeconds));
    }
}
