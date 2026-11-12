<?php

namespace App\Console\Commands;

use App\Services\ExpiryReminderMailer;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckConfiguration extends Command
{
    protected $signature = 'myidlers:check-config';
    protected $description = 'Check upgrade prerequisites without showing secrets or changing configuration';

    public function handle(ExpiryReminderMailer $mailer): int
    {
        $errors = 0;
        if (version_compare(PHP_VERSION, '8.3.0', '<')) {
            $this->error('PHP 8.3 or newer is required.');
            $errors++;
        } else {
            $this->info('PHP version: OK');
        }
        $extensions = ['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'json', 'mbstring', 'openssl', 'pcre', 'pdo', 'pdo_mysql', 'session', 'tokenizer', 'xml'];
        $missing = array_values(array_filter($extensions, fn ($extension) => ! extension_loaded($extension)));
        if ($missing) {
            $this->error('Missing PHP extensions: '.implode(', ', $missing));
            $errors++;
        } else {
            $this->info('Required PHP extensions: OK');
        }
        $key = config('app.key');
        $cipher = strtolower((string) config('app.cipher'));
        $bytes = is_string($key) && str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        $length = match ($cipher) { 'aes-256-cbc', 'aes-256-gcm' => 32, 'aes-128-cbc', 'aes-128-gcm' => 16, default => 0 };
        if (! is_string($bytes) || $length === 0 || strlen($bytes) !== $length) {
            $this->error('The application encryption key or cipher is missing or invalid. Do not generate a new key for an existing installation.');
            $errors++;
        } elseif (hash_equals('737774b9dc18c68346a366d866ef1424a4a0a6722f7a651de3fc2f7c8fca86d6', hash('sha256', $key))) {
            $this->error('The application encryption key matches a publicly published example. Plan a safe key migration; do not replace the key without preserving encrypted data.');
            $errors++;
        } else {
            $this->info('Application encryption configuration: present');
        }
        if (parse_url((string) config('app.url'), PHP_URL_SCHEME) !== 'https') {
            $this->warn('APP_URL does not use HTTPS. Verify the existing public URL before enabling reminders.');
        } else {
            $this->info('Public URL scheme: HTTPS');
        }
        try {
            $connection = DB::connection();
            if ($connection->getDriverName() !== 'mysql') {
                throw new \RuntimeException('Unsupported driver');
            }
            $connection->getPdo();
            $schema = $connection->getSchemaBuilder();
            if (! $schema->hasTable('migrations') || ! $schema->hasTable('expiry_reminder_deliveries') || ! $schema->hasColumn('settings', 'expiry_reminder_email')) {
                $this->warn('The 4.2 database migration is not installed. Use the backup-first upgrade procedure.');
            } else {
                $this->info('Database connection and reminder schema: OK');
                $settings = $connection->table('settings')->where('id', 1)->first(['expiry_reminders_enabled', 'expiry_reminder_email', 'expiry_reminder_timezone']);
                if ($settings && $settings->expiry_reminders_enabled && (! filter_var($settings->expiry_reminder_email, FILTER_VALIDATE_EMAIL) || ! in_array($settings->expiry_reminder_timezone, DateTimeZone::listIdentifiers(), true))) {
                    $this->warn('The reminder recipient or time zone needs attention in Settings.');
                }
            }
        } catch (Throwable $e) {
            $this->error('Could not verify the MySQL/MariaDB connection. Check the existing database configuration and PHP driver.');
            $errors++;
        }
        try {
            $mailer->validateConfiguration();
            $this->info('Reminder SMTP host and sender: configured (delivery not tested)');
        } catch (Throwable $e) {
            $this->warn('Reminder SMTP settings are incomplete. Configure the existing .env before sending mail.');
        }
        if ($this->laravel->configurationIsCached()) {
            $this->warn('Configuration is cached. Changes to .env require refreshing the config cache.');
        }
        $this->line('Scheduler/cron and actual email delivery are not checked by this command.');
        $this->line('No .env values or secrets were displayed or modified.');
        return $errors ? self::FAILURE : self::SUCCESS;
    }
}
