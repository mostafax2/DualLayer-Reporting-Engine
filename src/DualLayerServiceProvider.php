<?php

namespace Mostafax\DualLayer;

use Illuminate\Support\ServiceProvider;
use Mostafax\DualLayer\Application\SyncEngine;
use Mostafax\DualLayer\Contracts\IdempotencyStoreInterface;
use Mostafax\DualLayer\Contracts\RetrySchedulerInterface;
use Mostafax\DualLayer\Contracts\SourceDriverInterface;
use Mostafax\DualLayer\Contracts\TargetDriverInterface;
use Mostafax\DualLayer\Domain\SyncOperation\Repositories\SyncOperationRepositoryInterface;
use Mostafax\DualLayer\Infrastructure\Persistence\Cache\CacheIdempotencyStore;
use Mostafax\DualLayer\Infrastructure\Persistence\Eloquent\Repositories\EloquentSyncOperationRepository;
use Mostafax\DualLayer\Infrastructure\Scheduling\QueueRetryScheduler;
use Mostafax\DualLayer\Infrastructure\Sources\EloquentSourceDriver;
use Mostafax\DualLayer\Infrastructure\Targets\MongoDBTargetDriver;
use Mostafax\DualLayer\Infrastructure\Targets\NullTargetDriver;

final class DualLayerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/dual-layer.php', 'dual-layer');

        $this->app->singleton(SyncOperationRepositoryInterface::class,
            EloquentSyncOperationRepository::class);

        $this->app->singleton(SourceDriverInterface::class,
            EloquentSourceDriver::class);

        $this->app->singleton(IdempotencyStoreInterface::class, function ($app) {
            return new CacheIdempotencyStore(
                $app['cache']->store(config('dual-layer.idempotency.store', 'redis'))
            );
        });

        $this->app->singleton(TargetDriverInterface::class, function ($app) {
            $driver = config('dual-layer.target.driver', 'mongodb');

            return match ($driver) {
                'mongodb' => new MongoDBTargetDriver(
                    config('dual-layer.target.connection', 'mongodb')
                ),
                'null'    => new NullTargetDriver(),
                default   => $app->make($driver),
            };
        });

        $this->app->singleton(RetrySchedulerInterface::class, QueueRetryScheduler::class);

        $this->app->singleton(SyncEngine::class, function ($app) {
            return new SyncEngine(
                $app->make(SourceDriverInterface::class),
                $app->make(TargetDriverInterface::class),
                $app->make(IdempotencyStoreInterface::class),
                $app->make(SyncOperationRepositoryInterface::class),
                $app->make(RetrySchedulerInterface::class),
            );
        });

        $this->app->singleton('dual-layer.manager', function ($app) {
            return new DualLayerManager($app, $app->make(SyncEngine::class));
        });

        $this->app->alias('dual-layer.manager', DualLayerManager::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/dual-layer.php' => config_path('dual-layer.php'),
        ], 'dual-layer-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'dual-layer-migrations');

        if (config('dual-layer.auto_migrate', false)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\DualReportInstallCommand::class,
                Console\Commands\DualReportStatusCommand::class,
                Console\Commands\DualReportReprocessCommand::class,
                Console\Commands\DualReportSyncCommand::class,
            ]);
        }
    }
}
