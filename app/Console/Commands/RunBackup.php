<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class RunBackup extends Command
{
    protected $signature = 'autoshop:backup {--scheduled : Only run when scheduled backups are enabled}';

    protected $description = 'Create a gzipped MySQL backup in storage/app/private/backups and prune old ones';

    public function handle(BackupService $backups): int
    {
        if ($this->option('scheduled')) {
            if (! settings()->bool('backup.enabled')) {
                return self::SUCCESS;
            }
            if (settings('backup.frequency') === 'weekly' && ! now()->isSunday()) {
                return self::SUCCESS;
            }
        }
        try {
            $file = $backups->run();
            $pruned = $backups->prune((int) settings('backup.keep_days', 14));
            $this->info("Backup created: {$file}. Pruned {$pruned} old backup(s).");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
