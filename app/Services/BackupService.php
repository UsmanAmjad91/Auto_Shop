<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * MySQL backups via mysqldump into storage/app/private/backups (gzip).
 * Restore with: gunzip < file.sql.gz | mysql -u USER -p DATABASE
 */
class BackupService
{
    public const DIR = 'backups';

    public function run(): string
    {
        $c = config('database.connections.'.config('database.default'));
        if (($c['driver'] ?? '') !== 'mysql' && ($c['driver'] ?? '') !== 'mariadb') {
            throw new RuntimeException('Automated backups support MySQL/MariaDB only.');
        }

        Storage::disk('local')->makeDirectory(self::DIR);
        $file = self::DIR.'/backup-'.now()->format('Y-m-d_His').'.sql.gz';
        $path = Storage::disk('local')->path($file);

        // Password passed through the environment, never on the command line.
        $cmd = [env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'), '--single-transaction', '--quick', '--routines', '--no-tablespaces',
            '-h', $c['host'], '-P', (string) $c['port'], '-u', $c['username'], $c['database']];
        $process = new Process($cmd, null, ['MYSQL_PWD' => (string) $c['password']]);
        $process->setTimeout(3600);

        $gz = gzopen($path, 'wb6');
        $process->run(function ($type, $buffer) use ($gz) {
            if ($type === Process::OUT) {
                gzwrite($gz, $buffer);
            }
        });
        gzclose($gz);

        if (! $process->isSuccessful()) {
            @unlink($path);
            throw new RuntimeException('Backup failed: '.trim($process->getErrorOutput()));
        }
        AuditLogger::log('backup', null, 'Database backup created: '.basename($file));

        return $file;
    }

    public function list(): array
    {
        return collect(Storage::disk('local')->files(self::DIR))
            ->filter(fn ($f) => str_ends_with($f, '.sql.gz'))
            ->map(fn ($f) => ['file' => basename($f), 'size' => Storage::disk('local')->size($f), 'date' => Storage::disk('local')->lastModified($f)])
            ->sortByDesc('date')->values()->all();
    }

    public function prune(int $keepDays): int
    {
        $n = 0;
        foreach ($this->list() as $b) {
            if ($b['date'] < now()->subDays($keepDays)->timestamp) {
                Storage::disk('local')->delete(self::DIR.'/'.$b['file']);
                $n++;
            }
        }

        return $n;
    }
}
