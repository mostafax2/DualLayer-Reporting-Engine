<?php

namespace Mostafax\DualLayer\Console\Commands;

use Illuminate\Console\Command;
use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\ModelReference;
use Mostafax\DualLayer\Infrastructure\Jobs\ProcessSyncJob;

class DualReportSyncCommand extends Command
{
    protected $signature = 'dual-report:sync
                            {model : Fully-qualified Eloquent model class to sync}
                            {--chunk=500 : Number of records per batch}';

    protected $description = 'Dispatch sync jobs for all existing records of a model (initial or catch-up sync)';

    public function handle(): int
    {
        $modelClass = $this->argument('model');
        $chunk      = (int) $this->option('chunk');
        $queue      = config('dual-layer.queue.name', 'dual-layer-sync');

        if (! class_exists($modelClass)) {
            $this->error("Class [{$modelClass}] not found.");
            return self::FAILURE;
        }

        $total = $modelClass::count();

        if ($total === 0) {
            $this->info("No records found for [{$modelClass}].");
            return self::SUCCESS;
        }

        $this->info("Dispatching sync jobs for {$total} records of [{$modelClass}]...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $modelClass::chunk($chunk, function ($records) use ($queue, $bar) {
            foreach ($records as $model) {
                $tenantId = method_exists($model, 'getTenantId')
                    ? $model->getTenantId()
                    : ($model->tenant_id ?? null);

                $ref = new ModelReference(
                    modelClass: get_class($model),
                    modelId:    $model->getKey(),
                    operation:  'updated',
                    updatedAt:  ($model->updated_at ?? $model->created_at ?? now())->toISOString(),
                    tenantId:   $tenantId,
                );

                ProcessSyncJob::dispatch($ref)->onQueue($queue);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Done. {$total} job(s) dispatched to queue [{$queue}].");

        return self::SUCCESS;
    }
}
