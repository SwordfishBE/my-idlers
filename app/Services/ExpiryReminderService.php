<?php

namespace App\Services;

use App\Mail\ExpiryReminder;
use App\Models\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ExpiryReminderService
{
    private const TYPES = [
        1 => ['servers', 'hostname', 'Server'],
        2 => ['shared_hosting', 'main_domain', 'Shared hosting'],
        3 => ['reseller_hosting', 'main_domain', 'Reseller hosting'],
        4 => ['domains', 'domain', 'Domain'],
        5 => ['misc_services', 'name', 'Misc service'],
        6 => ['seedboxes', 'title', 'Seedbox'],
    ];

    public function __construct(private ExpiryReminderMailer $mailer) {}

    /** Return the number of transport failures; each failed delivery can be retried. */
    public function sendDue(): int
    {
        $settings = Settings::query()->find(1);
        if (! $settings || ! $settings->expiry_reminders_enabled) {
            return 0;
        }
        $recipient = $this->recipient($settings);
        try {
            $localNow = CarbonImmutable::now($settings->expiry_reminder_timezone);
        } catch (Throwable $e) {
            throw new RuntimeException('The reminder time zone is invalid.');
        }
        if ($localNow->hour < 9) {
            return 0;
        }
        $this->mailer->validateConfiguration();
        $failures = 0;
        foreach ([14, 3, 1] as $days) {
            $date = $localNow->addDays($days)->toDateString();
            foreach (self::TYPES as $type => [$table, $nameColumn, $label]) {
                $query = DB::table('pricings as p')->join($table.' as service', 'p.service_id', '=', 'service.id')
                    ->where('p.service_type', $type)->where('p.active', 1)->where('service.active', 1)
                    ->where('p.next_due_date', $date)
                    ->select('p.service_id', 'p.price', 'p.currency', 'service.'.$nameColumn.' as service_name');
                if ($type === 4) {
                    $query->addSelect('service.extension');
                }
                foreach ($query->cursor() as $row) {
                    $name = $type === 4 ? $row->service_name.'.'.$row->extension : $row->service_name;
                    $message = new ExpiryReminder((string) $name, $label, $date, $days, (string) $row->price, (string) $row->currency);
                    if (! $this->deliver($row->service_id, $date, $days, $recipient, $message)) {
                        $failures++;
                    }
                }
            }
        }
        return $failures;
    }

    public function sendTest(): void
    {
        $settings = Settings::query()->findOrFail(1);
        $recipient = $this->recipient($settings);
        $this->mailer->validateConfiguration();
        $this->mailer->send($recipient, new ExpiryReminder('Example service', 'test service', CarbonImmutable::now('UTC')->addDays(14)->toDateString(), 14, '0.00', 'EUR', true));
    }

    private function recipient(Settings $settings): string
    {
        $recipient = trim((string) $settings->expiry_reminder_email);
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Configure a valid reminder recipient in Settings.');
        }
        return $recipient;
    }

    /** Claim each notification atomically; stale claims can be retried after interruption. */
    private function deliver(string $serviceId, string $dueDate, int $days, string $recipient, ExpiryReminder $message): bool
    {
        $key = ['service_id' => $serviceId, 'due_date' => $dueDate, 'days_before' => $days, 'recipient_hash' => hash('sha256', strtolower($recipient))];
        $token = (string) Str::uuid();
        $now = CarbonImmutable::now('UTC');
        $claimed = DB::table('expiry_reminder_deliveries')->insertOrIgnore($key + [
            'status' => 'sending', 'claim_token' => $token, 'claimed_at' => $now, 'sent_at' => null,
        ]);
        if (! $claimed) {
            $claimed = DB::table('expiry_reminder_deliveries')->where($key)
                ->where('status', 'sending')->where('claimed_at', '<', $now->subHour())
                ->update(['claim_token' => $token, 'claimed_at' => $now]);
        }
        if (! $claimed) {
            return true;
        }
        try {
            $this->mailer->send($recipient, $message);
        } catch (Throwable $e) {
            DB::table('expiry_reminder_deliveries')->where($key)->where('claim_token', $token)->where('status', 'sending')->delete();
            Log::error('Expiry reminder delivery failed.', ['exception' => get_class($e)]);
            return false;
        }
        $updated = DB::table('expiry_reminder_deliveries')->where($key)->where('claim_token', $token)
            ->where('status', 'sending')->update(['status' => 'sent', 'sent_at' => CarbonImmutable::now('UTC')]);
        if ($updated !== 1) {
            Log::error('Expiry reminder delivery could not be recorded.');
            return false;
        }
        return true;
    }
}
