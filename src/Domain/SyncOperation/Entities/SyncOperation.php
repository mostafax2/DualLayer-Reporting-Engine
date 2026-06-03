<?php

namespace Mostafax\DualLayer\Domain\SyncOperation\Entities;

use Mostafax\DualLayer\Domain\SyncOperation\Events\SyncCompleted;
use Mostafax\DualLayer\Domain\SyncOperation\Events\SyncFailed;
use Mostafax\DualLayer\Domain\SyncOperation\Events\SyncRetried;
use Mostafax\DualLayer\Domain\SyncOperation\Exceptions\SyncException;
use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\ModelReference;
use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\SyncId;
use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\SyncStatus;

final class SyncOperation
{
    private array $domainEvents = [];

    private function __construct(
        private readonly SyncId         $id,
        private readonly ModelReference $model,
        private SyncStatus              $status,
        private int                     $attempts,
        private readonly int            $maxAttempts,
        private ?string                 $lastError,
        private readonly \DateTimeImmutable $createdAt,
    ) {}

    public static function create(ModelReference $model, int $maxAttempts = 3): self
    {
        return new self(
            id:          $model->syncId(),
            model:       $model,
            status:      SyncStatus::PENDING,
            attempts:    0,
            maxAttempts: $maxAttempts,
            lastError:   null,
            createdAt:   new \DateTimeImmutable(),
        );
    }

    public function markProcessing(): void
    {
        $this->status = SyncStatus::PROCESSING;
        $this->attempts++;
    }

    public function markCompleted(): void
    {
        $this->status    = SyncStatus::COMPLETED;
        $this->lastError = null;
        $this->raise(new SyncCompleted($this->id->toString(), $this->model));
    }

    public function markFailed(string $error): void
    {
        $this->lastError = $error;

        if ($this->attempts >= $this->maxAttempts) {
            $this->status = SyncStatus::DEAD;
        } else {
            $this->status = SyncStatus::FAILED;
            $this->raise(new SyncFailed($this->id->toString(), $this->model, $error, $this->attempts));
        }
    }

    public function markSkipped(): void
    {
        $this->status = SyncStatus::SKIPPED;
    }

    public function canRetry(): bool
    {
        return $this->status->canRetry() && $this->attempts < $this->maxAttempts;
    }

    public function backoffSeconds(): int
    {
        // Exponential: 30 → 90 → 270
        return (int) min(30 * (3 ** ($this->attempts - 1)), 900);
    }

    // ── Accessors ──────────────────────────────────────────────────────────

    public function id(): SyncId             { return $this->id; }
    public function model(): ModelReference  { return $this->model; }
    public function status(): SyncStatus     { return $this->status; }
    public function attempts(): int          { return $this->attempts; }
    public function lastError(): ?string     { return $this->lastError; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }

    public function pullDomainEvents(): array
    {
        $events            = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    private function raise(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
