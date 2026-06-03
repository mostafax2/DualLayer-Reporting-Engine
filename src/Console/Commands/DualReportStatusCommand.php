<?php

namespace Mostafax\DualLayer\Console\Commands;

use Illuminate\Console\Command;
use Mostafax\DualLayer\Domain\SyncOperation\Repositories\SyncOperationRepositoryInterface;
use Mostafax\DualLayer\Domain\SyncOperation\ValueObjects\SyncStatus;
use Mostafax\DualLayer\Contracts\TargetDriverInterface;

class DualReportStatusCommand extends Command
{
    protected $signature   = 'dual-report:status';
    protected $description = 'Show DualLayer sync queue and operation statistics';

    public function handle(
        SyncOperationRepositoryInterface $repo,
        TargetDriverInterface $target,
    ): void {
        $this->info('DualLayer Reporting Engine — Status');
        $this->line(str_repeat('─', 45));

        $rows = [];
        foreach (SyncStatus::cases() as $status) {
            $rows[] = [$status->value, number_format($repo->countByStatus($status))];
        }
        $this->table(['Status', 'Count'], $rows);

        $this->newLine();
        $ping = $target->ping();
        $this->line('Target (MongoDB): ' . ($ping ? '<fg=green>✓ reachable</>' : '<fg=red>✗ unreachable</>'));

        $dead = $repo->countByStatus(SyncStatus::DEAD);
        if ($dead > 0) {
            $this->newLine();
            $this->warn("{$dead} dead-letter operations. Run: php artisan dual-report:reprocess --dead");
        }
    }
}
