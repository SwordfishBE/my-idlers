<?php

namespace App\Console\Commands;

use App\Services\UpgradeDatabaseBackup;
use App\Services\UpgradeMigrationReview;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Throwable;

class UpgradeApplication extends Command
{
    protected $signature = 'myidlers:upgrade {--backup-dir= : Existing private directory for the SQL backup} {--writes-stopped : Confirm that all other database writers and schema changes are stopped}';

    protected $description = 'Back up the existing MySQL database, then run pending migrations';

    public function handle(UpgradeDatabaseBackup $backup, UpgradeMigrationReview $review, Migrator $migrator): int
    {
        $directory = $this->option('backup-dir');
        if (! is_string($directory) || ! str_starts_with($directory, '/')) {
            $this->error('Provide an absolute private backup directory with --backup-dir=/path/to/backups.');
            return self::FAILURE;
        }
        if (! $this->laravel->isDownForMaintenance()) {
            $this->error('Put the application into maintenance mode before upgrading.');
            return self::FAILURE;
        }
        if (! $this->option('writes-stopped') && ! $this->confirm('Have all queue workers, scheduled jobs, and external database writers been stopped?', false)) {
            $this->error('Stop all other database writes before upgrading.');
            return self::FAILURE;
        }
        if ($this->laravel->configurationIsCached()) {
            $this->error('Configuration is cached. Clear the configuration cache and rerun this command so the current environment is used.');
            return self::FAILURE;
        }

        $directory = $this->privateBackupDirectory($directory);
        if (! $directory) {
            $this->error('The backup directory must exist, be writable, be private (mode 0700), and be outside the application and public directories.');
            return self::FAILURE;
        }
        $lock = $this->acquireUpgradeLock();
        if (! $lock) {
            $this->error('Unable to acquire the upgrade lock.');
            return self::FAILURE;
        }
        try {
            try {
                $path = $backup->create($directory);
            } catch (Throwable $exception) {
                $this->error('Backup failed: '.$exception->getMessage());
                $this->error('No migrations were run. Keep the application in maintenance mode.');
                return self::FAILURE;
            }

            $this->info('Database backup created: '.$path);
            $this->warn('Keep this backup private and verify a restore to a separate database.');
            try {
                $review->verify($migrator);
            } catch (Throwable $exception) {
                $this->error('Migration review failed: '.$exception->getMessage());
                $this->error('No migrations were run. Keep the application in maintenance mode.');
                return self::FAILURE;
            }
            if (! $this->confirm('Have you restored this backup to a separate database and checked its contents?', false)) {
                $this->error('Backup verification was not confirmed. No migrations were run.');
                return self::FAILURE;
            }
            try {
                $result = $this->call('migrate', ['--force' => true]);
            } catch (Throwable $exception) {
                $this->error('Migration failed. Keep the application stopped and inspect the database before attempting recovery.');
                return self::FAILURE;
            }
            if ($result !== self::SUCCESS) {
                $this->error('Migration failed. Keep the application stopped and inspect the database before attempting recovery.');
                return self::FAILURE;
            }
            $this->info('Upgrade complete. The application remains in maintenance mode for verification.');
            return self::SUCCESS;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    protected function privateBackupDirectory(string $directory): ?string
    {
        $directory = realpath($directory);
        $public = realpath(public_path());
        $project = realpath(base_path());
        if (! $directory || ! is_dir($directory) || ! is_writable($directory) || (fileperms($directory) & 0077)
            || ($public && ($directory === $public || str_starts_with($directory, $public.DIRECTORY_SEPARATOR)))
            || ($project && ($directory === $project || str_starts_with($directory, $project.DIRECTORY_SEPARATOR)))) {
            return null;
        }
        return $directory;
    }

    protected function acquireUpgradeLock()
    {
        $lockPath = storage_path('framework/myidlers-upgrade.lock');
        if (is_link($lockPath)) {
            return false;
        }
        $lock = @fopen($lockPath, 'c');
        if (! $lock || ! chmod($lockPath, 0600) || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) { fclose($lock); }
            return false;
        }
        return $lock;
    }
}
