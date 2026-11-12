<?php

namespace Tests\Feature;

use App\Mail\ExpiryReminder;
use App\Models\Settings;
use App\Models\User;
use App\Services\ExpiryReminderMailer;
use App\Services\ExpiryReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExpiryReminderTest extends TestCase
{
    use RefreshDatabase;

    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-06-01 10:00', 'Europe/Brussels'));
        Settings::create(['id' => 1, 'expiry_reminders_enabled' => 1,
            'expiry_reminder_email' => 'notify@example.org', 'expiry_reminder_timezone' => 'Europe/Brussels']);
        DB::table('providers')->insert(['id' => 1, 'name' => 'Provider']);
        DB::table('locations')->insert(['id' => 1, 'name' => 'Location']);
        DB::table('os')->insert(['id' => 1, 'name' => 'OS']);
        $sender = $this->mock(ExpiryReminderMailer::class);
        $sender->shouldReceive('validateConfiguration')->zeroOrMoreTimes();
        $sender->shouldReceive('send')->zeroOrMoreTimes()->andReturnUsing(function ($recipient, $message) {
            $this->sent[] = [$recipient, $message];
        });
    }

    private function addService(string $id, int $type, string $date, int $active = 1, int $pricingActive = 1): void
    {
        DB::table('pricings')->insert(['service_id' => $id, 'service_type' => $type, 'active' => $pricingActive,
            'currency' => 'EUR', 'price' => 12.50, 'term' => 1, 'as_usd' => 12.50, 'usd_per_month' => 12.50, 'next_due_date' => $date]);
        [$table, $values] = match ($type) {
            1 => ['servers', ['hostname' => 'server.example', 'provider_id' => 1, 'location_id' => 1, 'os_id' => 1]],
            2 => ['shared_hosting', ['main_domain' => 'shared.example', 'provider_id' => 1, 'location_id' => 1]],
            3 => ['reseller_hosting', ['main_domain' => 'reseller.example', 'provider_id' => 1, 'location_id' => 1]],
            4 => ['domains', ['domain' => 'example', 'extension' => 'org', 'provider_id' => 1]],
            5 => ['misc_services', ['name' => 'Other service']],
            6 => ['seedboxes', ['title' => 'Seedbox', 'provider_id' => 1, 'location_id' => 1]],
        };
        DB::table($table)->insert(['id' => $id, 'active' => $active] + $values);
    }

    public function test_all_six_service_types_send_only_at_the_requested_intervals(): void
    {
        foreach (range(1, 6) as $type) {
            $this->addService(sprintf('%08d', $type), $type, '2026-06-15');
        }
        $this->addService('00000007', 4, '2026-06-04');
        $this->addService('00000008', 5, '2026-06-02');
        $this->addService('00000009', 5, '2026-06-03');
        $this->addService('00000010', 5, '2026-06-15', 0);
        $this->addService('00000011', 5, '2026-06-15', 1, 0);
        $service = $this->app->make(ExpiryReminderService::class);
        $this->assertSame(0, $service->sendDue());
        $this->assertCount(8, $this->sent);
        $this->assertContains('example.org', array_map(fn ($item) => $item[1]->serviceName, $this->sent));
        $this->assertSame([14, 14, 14, 14, 14, 14, 3, 1], array_map(fn ($item) => $item[1]->daysBefore, $this->sent));
        $this->assertSame('notify@example.org', $this->sent[0][0]);
        $service->sendDue();
        $this->assertCount(8, $this->sent, 'Repeating the job must not resend completed reminders.');
    }

    public function test_disabled_or_before_nine_local_time_sends_nothing(): void
    {
        $this->addService('00000001', 5, '2026-06-15');
        $this->travelTo(CarbonImmutable::parse('2026-06-01 08:59', 'Europe/Brussels'));
        $this->app->make(ExpiryReminderService::class)->sendDue();
        $this->assertCount(0, $this->sent);
        $this->travelTo(CarbonImmutable::parse('2026-06-01 10:00', 'Europe/Brussels'));
        DB::table('settings')->where('id', 1)->update(['expiry_reminders_enabled' => 0]);
        $this->app->make(ExpiryReminderService::class)->sendDue();
        $this->assertCount(0, $this->sent);
    }

    public function test_failed_delivery_can_be_retried(): void
    {
        $this->addService('00000001', 5, '2026-06-15');
        $sender = $this->mock(ExpiryReminderMailer::class);
        $sender->shouldReceive('validateConfiguration')->twice();
        $sender->shouldReceive('send')->once()->andThrow(new \RuntimeException('simulated'));
        $sender->shouldReceive('send')->once();
        $service = $this->app->make(ExpiryReminderService::class);
        $this->assertSame(1, $service->sendDue());
        $this->assertDatabaseCount('expiry_reminder_deliveries', 0);
        $this->assertSame(0, $service->sendDue());
        $this->assertDatabaseHas('expiry_reminder_deliveries', ['status' => 'sent']);
    }

    public function test_settings_require_authentication_and_validate_recipient_and_timezone(): void
    {
        $url = route('settings.reminders.update');
        $this->put($url, [])->assertRedirect(route('login'));
        $user = User::factory()->create();
        $this->actingAs($user)->put($url, ['expiry_reminders_enabled' => 1, 'expiry_reminder_email' => 'invalid', 'expiry_reminder_timezone' => 'bad zone'])
            ->assertSessionHasErrors(['expiry_reminder_email', 'expiry_reminder_timezone']);
        $this->actingAs($user)->put($url, ['expiry_reminders_enabled' => 1, 'expiry_reminder_email' => 'me@example.org', 'expiry_reminder_timezone' => 'Europe/Brussels'])
            ->assertRedirect(route('settings.index'));
        $this->assertDatabaseHas('settings', ['id' => 1, 'expiry_reminders_enabled' => 1, 'expiry_reminder_email' => 'me@example.org']);
    }

    public function test_inflight_claims_are_not_resent_but_stale_claims_are_retried(): void
    {
        $this->addService('00000001', 5, '2026-06-15');
        DB::table('expiry_reminder_deliveries')->insert([
            'service_id' => '00000001', 'due_date' => '2026-06-15', 'days_before' => 14,
            'recipient_hash' => hash('sha256', 'notify@example.org'), 'status' => 'sending',
            'claim_token' => 'old-token', 'claimed_at' => CarbonImmutable::now('UTC'),
        ]);
        $service = $this->app->make(ExpiryReminderService::class);
        $service->sendDue();
        $this->assertCount(0, $this->sent);
        DB::table('expiry_reminder_deliveries')->update(['claimed_at' => CarbonImmutable::now('UTC')->subHours(2)]);
        $service->sendDue();
        $this->assertCount(1, $this->sent);
        $this->assertDatabaseHas('expiry_reminder_deliveries', ['service_id' => '00000001', 'status' => 'sent']);
    }

    public function test_settings_screen_displays_reminder_controls(): void
    {
        \Illuminate\Support\Facades\Cache::put('currency_rates', (object) ['EUR' => 1], 60);
        $this->actingAs(User::factory()->create())->get(route('settings.index'))
            ->assertOk()->assertSee('Expiry Email Reminders')->assertSee('notify@example.org');
    }

    public function test_smtp_transport_requires_tls_and_an_explicit_sender(): void
    {
        config(['mail.mailers.expiry_reminders.host' => 'smtp.example.org', 'mail.mailers.expiry_reminders.port' => 587,
            'mail.from.address' => 'sender@example.org']);
        $sender = new ExpiryReminderMailer();
        $sender->validateConfiguration();
        $transport = $this->app['mail.manager']->mailer('expiry_reminders')->getSymfonyTransport();
        $this->assertTrue($transport->isTlsRequired());
        config(['mail.from.address' => 'hello@example.com']);
        $this->expectException(\RuntimeException::class);
        $sender->validateConfiguration();
    }

    public function test_test_message_is_marked_as_an_example(): void
    {
        $this->app->make(ExpiryReminderService::class)->sendTest();
        $this->assertCount(1, $this->sent);
        $this->assertTrue($this->sent[0][1]->isTest);
        $this->assertStringContainsString('test message', $this->sent[0][1]->render());
    }

    public function test_new_due_date_gets_a_new_reminder(): void
    {
        $this->addService('00000001', 5, '2026-06-15');
        $service = $this->app->make(ExpiryReminderService::class);
        $service->sendDue();
        DB::table('pricings')->where('service_id', '00000001')->update(['next_due_date' => '2026-06-04']);
        $service->sendDue();
        $this->assertCount(2, $this->sent);
        $this->assertSame([14, 3], array_map(fn ($item) => $item[1]->daysBefore, $this->sent));
    }

    public function test_test_email_button_requires_authentication_and_uses_saved_recipient(): void
    {
        $url = route('settings.reminders.test');
        $this->post($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->post($url)->assertRedirect(route('settings.index'))->assertSessionHas('success');
        $this->assertCount(1, $this->sent);
        $this->assertSame('notify@example.org', $this->sent[0][0]);
        $this->assertTrue($this->sent[0][1]->isTest);
    }

    public function test_test_email_button_is_rate_limited(): void
    {
        $this->actingAs(User::factory()->create());
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('settings.reminders.test'))->assertRedirect(route('settings.index'));
        }
        $this->post(route('settings.reminders.test'))->assertTooManyRequests();
        $this->assertCount(3, $this->sent);
    }

    public function test_reminders_are_scheduled_every_fifteen_minutes(): void
    {
        $events = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class)->events();
        $matching = array_values(array_filter($events, fn ($event) => str_contains($event->command ?? '', 'reminders:send')));
        $this->assertCount(1, $matching);
        $this->assertSame('*/15 * * * *', $matching[0]->expression);
        $this->assertTrue($matching[0]->withoutOverlapping);
    }

    public function test_email_escapes_service_names_and_contains_plain_text(): void
    {
        $message = new ExpiryReminder('<script>alert(1)</script>', 'Domain', '2026-06-15', 14, '12.50', 'EUR');
        $html = $message->render();
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('2026-06-15', $html);
        $this->assertSame('emails.expiry-reminder-text', $message->build()->textView);
    }
}
