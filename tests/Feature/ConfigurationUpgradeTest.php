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

    public function test_private_previous_key_can_decrypt_existing_data_after_planned_rotation(): void
    {
        $old = random_bytes(32);
        $new = random_bytes(32);
        $ciphertext = (new \Illuminate\Encryption\Encrypter($old, 'AES-256-CBC'))->encryptString('existing data');
        config(['app.key' => 'base64:'.base64_encode($new), 'app.previous_keys' => ['base64:'.base64_encode($old)]]);
        $this->app->forgetInstance('encrypter');
        $encrypter = $this->app->make('encrypter');
        $this->assertSame('existing data', $encrypter->decryptString($ciphertext));
        $fresh = $encrypter->encryptString('new data');
        $this->assertSame('new data', (new \Illuminate\Encryption\Encrypter($new, 'AES-256-CBC'))->decryptString($fresh));
    }

    public function test_public_key_as_previous_key_is_reported_without_disclosure(): void
    {
        $lines = file(base_path('.env.production'), FILE_IGNORE_NEW_LINES);
        $line = current(array_filter($lines, fn ($line) => str_starts_with($line, 'APP_KEY=')));
        $key = substr($line, strlen('APP_KEY='));
        config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32)), 'app.previous_keys' => [$key]]);
        Artisan::call('myidlers:check-config');
        $this->assertStringContainsString('previous key matches a public example', Artisan::output());
        $this->assertStringNotContainsString($key, Artisan::output());
    }

    public function test_local_postfix_is_explicit_and_default_smtp_requires_tls(): void
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo json_encode(config('mail.mailers.expiry_reminders'));
PHP;
        $local = new Process([PHP_BINARY, '-r', $code], base_path(), ['APP_ENV' => 'testing', 'REMINDER_MAIL_TRANSPORT' => 'local_postfix']);
        $local->mustRun();
        $this->assertSame(['transport' => 'sendmail', 'path' => '/usr/sbin/sendmail -bs'], json_decode($local->getOutput(), true));
        $default = new Process([PHP_BINARY, '-r', $code], base_path(), ['APP_ENV' => 'testing', 'REMINDER_MAIL_TRANSPORT' => false]);
        $default->mustRun();
        $config = json_decode($default->getOutput(), true);
        $this->assertSame('smtp', $config['transport']);
        $this->assertTrue($config['require_tls']);
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
