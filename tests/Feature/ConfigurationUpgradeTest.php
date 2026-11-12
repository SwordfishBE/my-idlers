<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConfigurationUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_configuration_check_never_prints_secret_values(): void
    {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32)),
            'mail.mailers.expiry_reminders.host' => 'smtp.example.org',
            'mail.mailers.expiry_reminders.password' => 'dont-print-this-password',
            'database.connections.sqlite.password' => 'dont-print-db-password',
            'mail.from.address' => 'sender@example.org']);
        $this->assertSame(1, Artisan::call('myidlers:check-config'));
        $output = Artisan::output();
        $this->assertStringNotContainsString('dont-print-this-password', $output);
        $this->assertStringNotContainsString('dont-print-db-password', $output);
        $this->assertStringNotContainsString(base64_encode(str_repeat('x', 32)), $output);
        $this->assertStringContainsString('No .env values or secrets were displayed or modified.', $output);
    }

    public function test_public_example_key_is_detected_without_printing_it(): void
    {
        $lines = file(base_path('.env.production'), FILE_IGNORE_NEW_LINES);
        $line = current(array_filter($lines, fn ($line) => str_starts_with($line, 'APP_KEY=')));
        $key = substr($line, strlen('APP_KEY='));
        config(['app.key' => $key]);
        Artisan::call('myidlers:check-config');
        $output = Artisan::output();
        $this->assertStringContainsString('matches a publicly published example', $output);
        $this->assertStringNotContainsString($key, $output);
    }

    public function test_legacy_mail_encryption_maps_to_the_new_tls_scheme(): void
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo config('mail.mailers.expiry_reminders.scheme');
PHP;
        $process = new Process([PHP_BINARY, '-r', $code], base_path(), ['APP_ENV' => 'testing', 'MAIL_ENCRYPTION' => 'ssl', 'MAIL_SCHEME' => false]);
        $process->mustRun();
        $this->assertSame('smtps', trim($process->getOutput()));
    }
}
