# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [Unreleased]

### Added

- **`SyncDead` domain event** — fired when an operation exhausts all retry attempts and moves to dead letter. Allows consumers to trigger alerts or custom logging.
- **`SyncRetried` event wired** — previously defined but never dispatched. Now fired by `SyncEngine` whenever a retry job is scheduled, carrying the attempt number and backoff seconds.
- **`dual-report:sync` Artisan command** — bulk/catch-up sync for pre-existing records. Dispatches `ProcessSyncJob` per record in configurable chunks with a live progress bar.
- **`TransformerInterface::sourceKey()`** — explicit declaration of which MySQL attribute name maps to `documentKey()`. Removes the previous implicit assumption that `source_id` always corresponds to `id`.
- **`SyncOperation::reconstitute()`** — public static factory for rehydrating entities from persistence. Replaces PHP Reflection in the repository.
- **`RetrySchedulerInterface`** — contract injected into `SyncEngine` for scheduling delayed retry jobs. Keeps the Application layer free of Infrastructure (`ProcessSyncJob`) imports.
- **`SyncSkippedException`** — thrown by `SyncEngine` when a lifecycle hook returns `false`, caught in `process()` to set status to `SKIPPED` instead of `FAILED`.
- Full test suite: 20+ tests across Unit (Domain value objects, Entity state machine, Transformers) and Feature (SyncEngine, DualLayerManager facade, Artisan commands).
- `LICENSE` (MIT), `.gitignore`, GitHub Actions CI (PHP 8.2/8.3 × Laravel 11/12), `phpunit.xml`.

### Changed

- **`RedisIdempotencyStore` renamed to `CacheIdempotencyStore`** — the store has always accepted any Laravel cache driver (file, array, redis, dynamodb). The old name was misleading.
- **`SyncOperation::markFailed`** — now raises `SyncDead` (not silence) when the operation reaches dead-letter status, consistent with how `SyncFailed` is raised for retryable failures.

### Fixed

- `EloquentSourceDriver::fetch` — guarded `withTrashed()` call: models without `SoftDeletes` no longer throw `BadMethodCallException`.
- `SyncEngine::execute` — upsert key resolution replaced: was a brittle `documentKey() === 'source_id' ? 'id' : ...` heuristic, now uses `TransformerInterface::sourceKey()`.
- `SyncEngine` hook abort — previously returned silently; now marks the operation `SKIPPED` and persists the status change.
- `EloquentSyncOperationRepository::hydrate` — removed `ReflectionClass` / `setAccessible` pattern in favour of `SyncOperation::reconstitute()`.
- `SyncEngine` retry loop — was a direct `ProcessSyncJob::dispatch()` call inside the Application layer; now delegates to `RetrySchedulerInterface`.

---

## [1.0.0] — Initial release

- MySQL → MongoDB async sync engine for Laravel 10/11/12
- Event-driven capture via Eloquent Observers
- Idempotent processing via deterministic `SyncId` (xxh128 hash) + Redis/cache store
- Exponential-backoff retry (30s → 90s → 270s) with dead-letter tracking
- `DualReport` facade with `observe()`, `register()`, `transformer()`, `hooks()` API
- `ProcessSyncJob`, `SyncEngine`, pluggable `TransformerInterface` / `SyncHooksInterface`
- Artisan commands: `dual-report:install`, `dual-report:status`, `dual-report:reprocess`
- Multi-tenant ready (`tenant_id` propagated through the full pipeline)
- `NullTargetDriver` for test environments
