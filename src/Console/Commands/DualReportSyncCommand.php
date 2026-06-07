<?php

namespace Mostafax\DualLayer\Console\Commands;

use Illuminate\Console\Command;
use Mostafax\DualLayer\DualLayerManager;
use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\ModelReference;
use Mostafax\DualLayer\Infrastructure\Jobs\ProcessSyncJob;

class DualReportSyncCommand extends Command
{
    protected $signature = 'dual-report:sync
                            {model?            : Fully-qualified Eloquent model class to sync}
                            {--all             : Sync every model registered in config or via DualReport::observe()}
                            {--chunk=500       : Number of records per batch}
                            {--stop-on-error   : Abort the --all loop if any model fails}';

    protected $description = 'Dispatch sync jobs for all existing records of a model (or all models with --all)';

    public function handle(DualLayerManager $manager): int
    {
        if ($this->option('all')) {
            return $this->syncAll($manager);
        }

        $modelClass = $this->argument('model');

        if (! $modelClass) {
            $this->error('Provide a model class  OR  pass --all to sync every registered model.');
            $this->line('  php artisan dual-report:sync "App\Models\User"');
            $this->line('  php artisan dual-report:sync --all');
            return self::FAILURE;
        }

        return $this->syncOne($modelClass);
    }

    // ── --all ────────────────────────────────────────────────────────────────

    private function syncAll(DualLayerManager $manager): int
    {
        $models = $this->resolveAllModels($manager);

        if (empty($models)) {
            $this->error('No models found. Add them to config/dual-layer.php under "models", or register via DualReport::observe().');
            return self::FAILURE;
        }

        $this->info(sprintf('Syncing <fg=cyan>%d</> model(s):', count($models)));
        foreach ($models as $class) {
            $this->line("  • {$class}");
        }
        $this->newLine();

        $failed      = 0;
        $stopOnError = $this->option('stop-on-error');

        foreach ($models as $class) {
            $this->line("<fg=yellow>▶</> {$class}");

            if ($this->syncOne($class) !== self::SUCCESS) {
                $failed++;
                $this->warn("  ✗ Failed: {$class}");

                if ($stopOnError) {
                    $this->error('Stopped early (--stop-on-error).');
                    break;
                }
            }

            $this->newLine();
        }

        if ($failed > 0) {
            $this->warn("{$failed} model(s) had errors.");
            return self::FAILURE;
        }

        $this->info('All models synced successfully.');
        return self::SUCCESS;
    }

    // ── single model ─────────────────────────────────────────────────────────

    private function syncOne(string $modelClass): int
    {
        if (! class_exists($modelClass)) {
            $this->error("  Class [{$modelClass}] not found.");
            return self::FAILURE;
        }

        $exitCode = self::SUCCESS;

        try {
            $total = $modelClass::count();

            if ($total === 0) {
                $this->info("  No records found. Skipping.");
            } else {
                $this->dispatchChunked($modelClass, $total);
            }
        } catch (\Throwable $e) {
            $this->error("  Error [{$modelClass}]: {$e->getMessage()}");
            $exitCode = self::FAILURE;
        }

        return $exitCode;
    }

    // ── chunk dispatch ────────────────────────────────────────────────────────

    private function dispatchChunked(string $modelClass, int $total): void
    {
        $chunk = (int) $this->option('chunk');
        $queue = config('dual-layer.queue.name', 'dual-layer-sync');

        $this->info("  Dispatching {$total} record(s) → queue [{$queue}]");

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat('  %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%');
        $bar->start();

        $modelClass::chunk($chunk, function ($records) use ($queue, $bar) {
            foreach ($records as $model) {
                ProcessSyncJob::dispatch(new ModelReference(
                    modelClass: get_class($model),
                    modelId:    $model->getKey(),
                    operation:  'updated',
                    updatedAt:  ($model->updated_at ?? $model->created_at ?? now())->toISOString(),
                    tenantId:   $this->resolveTenantId($model),
                ))->onQueue($queue);

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("  ✓ {$total} job(s) dispatched.");
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /** @return string[] */
    private function resolveAllModels(DualLayerManager $manager): array
    {
        $fromConfig  = array_filter((array) config('dual-layer.models', []));
        $fromManager = $manager->observedModels();

        return array_values(array_unique(array_merge($fromConfig, $fromManager)));
    }

    private function resolveTenantId(mixed $model): ?string
    {
        if (method_exists($model, 'getTenantId')) {
            return $model->getTenantId();
        }

        return isset($model->tenant_id) ? (string) $model->tenant_id : null;
    }
}
