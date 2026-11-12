<?php

namespace Tests\Feature;

use App\Services\UpgradeDatabaseBackup;
use App\Services\UpgradeMigrationReview;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class UpgradeSafetyTest extends TestCase
{
    public function test_upgrade_refuses_to_run_without_a_private_backup_directory(): void
    {
        $this->mock(UpgradeDatabaseBackup::class)->shouldNotReceive('create');
        $this->artisan('myidlers:upgrade')->assertExitCode(1);
    }

    public function test_upgrade_refuses_to_run_without_maintenance_mode(): void
    {
        $this->mock(UpgradeDatabaseBackup::class)->shouldNotReceive('create');
        $this->artisan('myidlers:upgrade', ['--backup-dir' => '/tmp'])->assertExitCode(1);
    }

    private function testCommand(): TestableUpgradeApplication
    {
        $maintenance = \Mockery::mock(\Illuminate\Contracts\Foundation\MaintenanceMode::class);
        $maintenance->shouldReceive('active')->andReturn(true);
        $this->app->instance(\Illuminate\Contracts\Foundation\MaintenanceMode::class, $maintenance);
        $command = new TestableUpgradeApplication();
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->registerCommand($command);
        return $command;
    }

    public function test_failed_backup_never_runs_migrations(): void
    {
        $command = $this->testCommand();
        $this->mock(UpgradeDatabaseBackup::class)->shouldReceive('create')->once()->andThrow(new RuntimeException('test failure'));
        $this->mock(UpgradeMigrationReview::class)->shouldNotReceive('verify');
        $this->artisan('myidlers:upgrade', ['--backup-dir' => '/test', '--writes-stopped' => true])->assertExitCode(1);
        $this->assertFalse($command->migrated);
    }

    public function test_unverified_restore_never_runs_migrations(): void
    {
        $command = $this->testCommand();
        $this->mock(UpgradeDatabaseBackup::class)->shouldReceive('create')->once()->andReturn('/private/backup.sql');
        $this->mock(UpgradeMigrationReview::class)->shouldReceive('verify')->once();
        $this->artisan('myidlers:upgrade', ['--backup-dir' => '/test', '--writes-stopped' => true])
            ->expectsConfirmation('Have you restored this backup to a separate database and checked its contents?', 'no')
            ->assertExitCode(1);
        $this->assertFalse($command->migrated);
    }

    public function test_verified_backup_is_required_before_migrations(): void
    {
        $command = $this->testCommand();
        $this->mock(UpgradeDatabaseBackup::class)->shouldReceive('create')->once()->ordered()->andReturn('/private/backup.sql');
        $this->mock(UpgradeMigrationReview::class)->shouldReceive('verify')->once()->ordered();
        $this->artisan('myidlers:upgrade', ['--backup-dir' => '/test', '--writes-stopped' => true])
            ->expectsConfirmation('Have you restored this backup to a separate database and checked its contents?', 'yes')
            ->assertExitCode(0);
        $this->assertTrue($command->migrated);
    }

    public function test_backup_directory_inside_the_application_is_rejected(): void
    {
        $backup = new TestableBackupDirectory();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('outside the application');
        $backup->create(base_path('storage'));
    }

    public function test_reviewed_existing_migrations_have_unchanged_contents(): void
    {
        $migrator = $this->app->make(Migrator::class);
        $migrator->getRepository()->createRepository();
        $this->app->make(UpgradeMigrationReview::class)->verify($migrator);
        $this->assertTrue(Schema::hasTable('migrations'));
    }

    public function test_an_unreviewed_pending_migration_is_rejected(): void
    {
        $migrator = $this->app->make(Migrator::class);
        $migrator->getRepository()->createRepository();
        $dir = storage_path('framework/testing/upgrade-'.bin2hex(random_bytes(6)));
        mkdir($dir, 0700, true);
        $file = $dir.'/2099_01_01_000000_unreviewed.php';
        file_put_contents($file, '<?php // unreviewed test migration');
        $migrator->path($dir);
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('unreviewed or modified migration');
            $this->app->make(UpgradeMigrationReview::class)->verify($migrator);
        } finally {
            unlink($file);
            rmdir($dir);
        }
    }
}

class TestableUpgradeApplication extends \App\Console\Commands\UpgradeApplication
{
    public bool $migrated = false;
    protected function privateBackupDirectory(string $directory): ?string { return sys_get_temp_dir(); }
    protected function acquireUpgradeLock() { $file = tmpfile(); flock($file, LOCK_EX); return $file; }
    public function call($command, array $arguments = []) { $this->migrated = true; return 0; }
}

class TestableBackupDirectory extends UpgradeDatabaseBackup
{
    protected function supportsPrivateFiles(): bool { return true; }
    protected function isPrivateDirectory(string $directory): bool { return true; }
}
