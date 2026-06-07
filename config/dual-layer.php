<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Target driver — where synced data is written
    |--------------------------------------------------------------------------
    | 'mongodb' uses the mongodb/laravel-mongodb connection
    | 'null'    silently discards all writes (useful for tests/CI)
    | Any FQCN bound in the container is also accepted.
    */
    'target' => [
        'driver'     => env('DUAL_LAYER_TARGET', 'mongodb'),
        'connection' => env('DUAL_LAYER_MONGO_CONNECTION', 'mongodb'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'name'       => env('DUAL_LAYER_QUEUE', 'dual-layer-sync'),
        'connection' => env('DUAL_LAYER_QUEUE_CONNECTION', null), // null = default
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry policy
    |--------------------------------------------------------------------------
    | Backoff is exponential: 30s → 90s → 270s
    */
    'retry' => [
        'max_attempts' => (int) env('DUAL_LAYER_MAX_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency store
    |--------------------------------------------------------------------------
    | Must support put/has/forget. Redis is recommended.
    | 'file' works for single-server setups.
    */
    'idempotency' => [
        'store' => env('DUAL_LAYER_IDEMPOTENCY_STORE', 'redis'),
        'ttl'   => (int) env('DUAL_LAYER_IDEMPOTENCY_TTL', 86400), // 24h
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-run migrations on boot (use dual-report:install in production)
    |--------------------------------------------------------------------------
    */
    'auto_migrate' => (bool) env('DUAL_LAYER_AUTO_MIGRATE', false),

    /*
    |--------------------------------------------------------------------------
    | Registered Models (used by dual-report:sync --all)
    |--------------------------------------------------------------------------
    | List every Eloquent model class that should be synced to the target.
    | These are processed in order when --all is passed. Models registered
    | at runtime via DualReport::observe() are merged automatically.
    |
    | Example:
    |   App\Models\User::class,
    |   App\Models\Order::class,
    */
    'models' => [
        // App\Models\User::class,
        // App\Models\Order::class,
    ],

];
