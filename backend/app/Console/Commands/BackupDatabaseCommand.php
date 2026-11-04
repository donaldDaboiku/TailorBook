<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Dump the MySQL database to storage/app/private/backups';

    public function handle(): int
    {
        $driver = (string) config('database.default');

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->error('backup:database only supports MySQL.');

            return self::FAILURE;
        }

        $connection = config('database.connections.'.$driver);
        $directory = storage_path('app/private/backups');
        File::ensureDirectoryExists($directory);

        $file = $directory.'/tailormate-'.now()->format('Ymd-His').'.sql';
        $process = new Process([
            'mysqldump',
            '--host='.(string) $connection['host'],
            '--port='.(string) $connection['port'],
            '--user='.(string) $connection['username'],
            '--default-character-set=utf8mb4',
            '--single-transaction',
            '--result-file='.$file,
            (string) $connection['database'],
        ]);
        $process->setTimeout(300);
        $env = getenv();
        $env = is_array($env) ? $env : [];
        $env['MYSQL_PWD'] = (string) $connection['password'];
        $process->setEnv($env);

        $process->run();

        if (! $process->isSuccessful() || ! is_file($file) || filesize($file) === 0) {
            File::delete($file);
            $this->error(trim($process->getErrorOutput()) ?: 'mysqldump failed. Install the MySQL client on the server.');

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
