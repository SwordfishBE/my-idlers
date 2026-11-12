<?php

namespace App\Services;

use App\Mail\ExpiryReminder;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class ExpiryReminderMailer
{
    public function validateConfiguration(): void
    {
        $host = config('mail.mailers.expiry_reminders.host');
        $sender = config('mail.from.address');
        if (! is_string($sender)
            || ! filter_var($sender, FILTER_VALIDATE_EMAIL) || strtolower($sender) === 'hello@example.com') {
            throw new RuntimeException('Configure a real MAIL_FROM_ADDRESS before sending reminders.');
        }
        if (config('mail.mailers.expiry_reminders.transport') === 'sendmail') {
            if (! is_executable('/usr/sbin/sendmail')) {
                throw new RuntimeException('The local Postfix sendmail binary is not executable.');
            }
            return;
        }
        if (! is_string($host) || trim($host) === '') {
            throw new RuntimeException('Configure MAIL_HOST before sending reminders.');
        }
        $scheme = config('mail.mailers.expiry_reminders.scheme');
        if ($scheme !== null && ! in_array($scheme, ['smtp', 'smtps'], true)) {
            throw new RuntimeException('MAIL_SCHEME must be smtp or smtps.');
        }
    }

    public function send(string $recipient, ExpiryReminder $message): void
    {
        $sent = Mail::mailer('expiry_reminders')->to($recipient)->send($message);
        if ($sent === null) {
            throw new RuntimeException('The mail transport did not accept the message.');
        }
    }
}
