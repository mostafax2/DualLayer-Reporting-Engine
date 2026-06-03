<?php

namespace Mostafax\DualLayer\Console\Commands;

use Illuminate\Console\Command;

class DualReportInstallCommand extends Command
{
    protected $signature   = 'dual-report:install';
    protected $description = 'Install DualLayer Reporting Engine: publish config and run migrations';

    public function handle(): void
    {
        $this->info('Installing DualLayer Reporting Engine...');
        $this->call('vendor:publish', ['--tag' => 'dual-layer-config',     '--force' => false]);
        $this->call('vendor:publish', ['--tag' => 'dual-layer-migrations', '--force' => false]);
        $this->call('migrate');
        $this->info('Done.');
        $this->newLine();
        $this->line('Register models in AppServiceProvider:');
        $this->line('  DualReport::observe(User::class);');
        $this->line('  DualReport::register(Order::class, new OrderTransformer());');
    }
}
