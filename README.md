# DualLayer Reporting Engine

> Enterprise-grade MySQL → MongoDB async sync engine for Laravel.

[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-10%20|%2011%20|%2012%20|%2013-red.svg)](https://laravel.com)

---

## Architecture

```
┌──────────────────────────────────────────────────────────────────┐
│                         PRIMARY LAYER                            │
│                      MySQL (source of truth)                     │
│                                                                  │
│   User::create() → ModelSyncObserver fires                       │
│                         │                                        │
└─────────────────────────┼────────────────────────────────────────┘
                          │  dispatches
                          ▼
                 ┌─────────────────┐
                 │  ProcessSyncJob  │  (queued — Redis/SQS/DB)
                 └────────┬────────┘
                          │  calls
                          ▼
              ┌───────────────────────┐
              │      SyncEngine        │  ← orchestrator
              │  ┌─────────────────┐  │
              │  │ Idempotency     │  │  → skip if already processed
              │  │ check (Redis)   │  │
              │  └────────┬────────┘  │
              │           │           │
              │  ┌────────▼────────┐  │
              │  │ SourceDriver    │  │  → re-fetch from MySQL
              │  │ (Eloquent)      │  │
              │  └────────┬────────┘  │
              │           │           │
              │  ┌────────▼────────┐  │
              │  │ Transformer     │  │  → reshape for MongoDB
              │  │ (pluggable)     │  │
              │  └────────┬────────┘  │
              │           │           │
              │  ┌────────▼────────┐  │
              │  │ TargetDriver    │  │  → upsert / delete
              │  │ (MongoDB)       │  │
              │  └─────────────────┘  │
              └───────────────────────┘
                          │
                          ▼
┌──────────────────────────────────────────────────────────────────┐
│                       SECONDARY LAYER                            │
│                  MongoDB (analytics-optimised)                   │
└──────────────────────────────────────────────────────────────────┘
                          │
                   on failure:
                   exponential backoff (30s → 90s → 270s)
                   → dead letter after N attempts
                   → php artisan dual-report:reprocess --dead
```

---

## Installation

```bash
composer require mostafax/dual-layer-reporting-engine
php artisan dual-report:install
```

---

## Quick Start

### 1. Register models in `AppServiceProvider`

```php
use Mostafax\DualLayer\Support\Facades\DualReport;

public function boot(): void
{
    // Auto-detect transformer from DefaultTransformer (stores raw attributes)
    DualReport::observe(User::class);

    // Custom transformer
    DualReport::register(Order::class, new OrderTransformer());

    // With lifecycle hooks
    DualReport::observe(Product::class);
    DualReport::hooks(new ProductSyncHooks());
}
```

### 2. Create a custom transformer

```php
use Mostafax\DualLayer\Contracts\TransformerInterface;
use App\Models\Order;

class OrderTransformer implements TransformerInterface
{
    public function handles(): string    { return Order::class; }
    public function collection(): string { return 'orders'; }
    public function documentKey(): string { return 'source_id'; }  // MongoDB field name
    public function sourceKey(): string  { return 'id'; }          // MySQL attribute name

    public function transform(array $attr): array
    {
        return [
            'source_id'    => $attr['id'],
            'source_type'  => 'order',
            'customer_id'  => $attr['user_id'],
            'total'        => $attr['total'],
            'currency'     => $attr['currency'] ?? 'USD',
            'status'       => $attr['status'],
            'line_items'   => [],          // populated in a relation-aware transformer
            'placed_at'    => $attr['created_at'],
            'synced_at'    => now()->toISOString(),
            'meta' => [
                'tenant_id'     => $attr['tenant_id'] ?? null,
                'sync_version'  => $attr['updated_at'],
            ],
        ];
    }
}
```

### 3. Lifecycle hooks (optional)

```php
use Mostafax\DualLayer\Contracts\SyncHooksInterface;

class ProductSyncHooks implements SyncHooksInterface
{
    public function handles(): string { return Product::class; }

    public function beforeSync(string $operation, array $document): bool
    {
        // Return false to abort the sync for this record
        return $document['is_published'] ?? true;
    }

    public function afterSync(string $operation, array $document): void
    {
        Cache::forget("product:{$document['source_id']}");
    }
}
```

### 4. Multi-tenant model

```php
use Mostafax\DualLayer\Support\Traits\HasDualLayerSync;

class User extends Authenticatable
{
    use HasDualLayerSync;   // exposes getTenantId()

    // tenant_id column is automatically picked up by the observer
}
```

---

## MongoDB Document Schema

### Default envelope (DefaultTransformer)

```json
{
  "source_type": "user",
  "source_id": 42,
  "data": {
    "id": 42,
    "name": "Mostafa",
    "email": "mostafa@example.com",
    "created_at": "2024-01-01T00:00:00.000Z"
  },
  "synced_at": "2024-01-01T00:00:05.123Z"
}
```

### Custom transformer (OrderTransformer)

```json
{
  "source_id": 1001,
  "source_type": "order",
  "customer_id": 42,
  "total": 149.99,
  "currency": "USD",
  "status": "completed",
  "placed_at": "2024-01-01T00:00:00.000Z",
  "synced_at": "2024-01-01T00:00:05.123Z",
  "meta": {
    "tenant_id": "acme-corp",
    "sync_version": "2024-01-01T00:00:04.000Z"
  }
}
```

---

## CLI Commands

```bash
# Install (publish config + migrate)
php artisan dual-report:install

# Status dashboard
php artisan dual-report:status

# Initial / catch-up bulk sync for existing records
php artisan dual-report:sync "App\Models\User"
php artisan dual-report:sync "App\Models\Order" --chunk=1000

# Requeue failed operations
php artisan dual-report:reprocess --failed
php artisan dual-report:reprocess --dead
php artisan dual-report:reprocess --dead --limit=500
```

---

## Domain Events

Listen to any of these events in your `EventServiceProvider`:

| Event | Fired when |
|-------|-----------|
| `SyncCompleted` | Document successfully written to MongoDB |
| `SyncFailed` | Sync attempt failed — retry will be scheduled |
| `SyncRetried` | Retry job dispatched (includes attempt # and backoff seconds) |
| `SyncDead` | All retry attempts exhausted — operation moved to dead letter |

```php
use Mostafax\DualLayer\Domain\SyncOperation\Events\SyncDead;
use Mostafax\DualLayer\Domain\SyncOperation\Events\SyncFailed;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        SyncDead::class => [
            \App\Listeners\AlertOpsOnDeadSync::class,
        ],
        SyncFailed::class => [
            \App\Listeners\LogSyncFailure::class,
        ],
    ];
}
```

---

## Configuration

```env
DUAL_LAYER_TARGET=mongodb                  # mongodb | null
DUAL_LAYER_MONGO_CONNECTION=mongodb        # Laravel DB connection name
DUAL_LAYER_QUEUE=dual-layer-sync           # queue name
DUAL_LAYER_MAX_ATTEMPTS=3                  # retry limit
DUAL_LAYER_IDEMPOTENCY_STORE=redis         # redis | file
DUAL_LAYER_IDEMPOTENCY_TTL=86400           # 24h
```

---

## Idempotency

Every sync operation gets a deterministic `sync_id`:

```
sync_id = xxh128(model_class | model_id | operation | updated_at)
```

The same model state always produces the same `sync_id`. Before processing, the engine checks Redis — if the key exists, the job returns immediately. This means duplicate jobs from retry storms, observer double-fires, or deployment rollovers are all handled safely.

---

## Retry & Dead Letter

| Attempt | Backoff |
|---------|---------|
| 1st     | 30s     |
| 2nd     | 90s     |
| 3rd     | 270s    |
| 4th+    | → DEAD  |

Dead-letter ops are visible in `php artisan dual-report:status` and reprocessable on-demand.

---

## Scalability

| Tier | Jobs/day | Setup |
|---|---|---|
| Small | < 1M | 1 Redis + 3 workers |
| Medium | 1M–10M | Redis Sentinel + 10 workers + read replica |
| Large | 10M+ | Redis Cluster + Kubernetes HPA + MongoDB sharding |

Workers are **stateless** — scale horizontally without coordination.
