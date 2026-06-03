<?php

namespace Mostafax\DualLayer\Tests;

use Mostafax\DualLayer\DualLayerServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [DualLayerServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'DualReport' => \Mostafax\DualLayer\Support\Facades\DualReport::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Use null target so no real MongoDB is needed
        $app['config']->set('dual-layer.target.driver', 'null');

        // Use array cache for idempotency (no Redis needed)
        $app['config']->set('dual-layer.idempotency.store', 'array');

        // Run jobs synchronously in tests
        $app['config']->set('queue.default', 'sync');

        // In-memory SQLite for the audit table
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
