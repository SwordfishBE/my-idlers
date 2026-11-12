<?php

namespace App\Console\Commands;

use App\Services\ExpiryReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendExpiryReminders extends Command
{
    protected $signature = 'reminders:send {--test : Send one test message to the configured recipient}';
    protected $description = 'Send expiry reminders to the configured recipient';

    public function handle(ExpiryReminderService $reminders): int
    {
        try {
            if ($this->option('test')) {
                $reminders->sendTest();
                $this->info('Test reminder accepted by the mail server.');
                return self::SUCCESS;
            }
            $failures = $reminders->sendDue();
            if ($failures > 0) {
                $this->error('Some reminders could not be sent; the scheduler will retry.');
                return self::FAILURE;
            }
            return self::SUCCESS;
        } catch (Throwable $e) {
            Log::error('Expiry reminder job failed.', ['exception' => get_class($e)]);
            $this->error('Reminder job failed. Check the reminder, mail and scheduler configuration.');
            return self::FAILURE;
        }
    }
}
