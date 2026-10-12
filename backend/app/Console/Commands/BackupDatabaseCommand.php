<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Dump the PostgreSQL database to storage/app/private/backups';

    public function handle(): int
    {
        if (config('database.default') !== 'pgsql') {
            $this->error('backup:database only supports PostgreSQL.');

            return self::FAILURE;
        }

        $connection = config('database.connections.pgsql');
        $directory = storage_path('app/private/backups');
        File::ensureDirectoryExists($directory);

        $file = $directory.'/tailormate-'.now()->format('Ymd-His').'.sql';
        $process = new Process([
            'pg_dump',
            '--host', (string) $connection['host'],
            '--port', (string) $connection['port'],
            '--username', (string) $connection['username'],
            '--dbname', (string) $connection['database'],
            '--file', $file,
            '--no-owner',
            '--no-privileges',
        ]);
        $process->setTimeout(300);
        $env = getenv();
        $env = is_array($env) ? $env : [];
        $env['PGPASSWORD'] = (string) $connection['password'];
        $process->setEnv($env);

        $process->run();

        if (! $process->isSuccessful() || ! is_file($file) || filesize($file) === 0) {
            File::delete($file);
            $this->error(trim($process->getErrorOutput()) ?: 'pg_dump failed. Install postgresql-client on the server.');

            return self::FAILURE;
        }

        $dumps = collect(File::files($directory))
            ->filter(fn ($dump) => str_ends_with($dump->getFilename(), '.sql'))
            ->sortByDesc(fn ($dump) => $dump->getMTime())
            ->values();

        foreach ($dumps->slice(7) as $old) {
            File::delete($old->getPathname());
        }

        $this->info('Backup written to '.$file);

        return self::SUCCESS;
    }
}
