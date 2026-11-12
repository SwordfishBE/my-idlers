<?php

namespace Tests\Feature;

use App\Models\Locations;
use App\Models\OS;
use App\Models\Pricing;
use App\Models\Providers;
use App\Models\Server;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YabsAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['api_token' => 'ExampleTokenABC123']);
        Settings::create(['id' => 1]);
        $provider = Providers::create(['name' => 'Provider']);
        $location = Locations::create(['name' => 'Location']);
        $os = OS::create(['name' => 'Linux']);
        Pricing::create(['service_id' => 'testsvr1', 'service_type' => 1, 'currency' => 'EUR', 'price' => 5,
            'term' => 1, 'as_usd' => 5, 'usd_per_month' => 5, 'next_due_date' => '2027-01-01']);
        $this->server = Server::create(['id' => 'testsvr1', 'hostname' => 'server.example', 'server_type' => 1,
            'os_id' => $os->id, 'provider_id' => $provider->id, 'location_id' => $location->id,
            'ram' => 1024, 'disk' => 10, 'cpu' => 1]);
    }

    private function payload(): array
    {
        return [
            'time' => '20261112-120000', 'version' => 'test', 'net' => ['ipv4' => true, 'ipv6' => false],
            'os' => ['arch' => 'x86_64', 'distro' => 'Linux', 'kernel' => 'test', 'uptime' => '1 day'],
            'cpu' => ['model' => 'Test', 'cores' => 1, 'freq' => 1000, 'aes' => true, 'virt' => true],
            'mem' => ['ram' => 1048576, 'swap' => 0, 'disk' => 10485760], 'geekbench' => [],
            'fio' => array_map(fn ($size) => ['bs' => $size, 'speed_rw' => 10000], ['4k', '64k', '512k', '1m']),
            'iperf' => [],
        ];
    }

    public function test_bearer_route_saves_a_valid_yabs_result(): void
    {
        $this->withToken($this->user->api_token)->postJson('/api/yabs/testsvr1', $this->payload())->assertOk();
        $this->assertDatabaseHas('yabs', ['server_id' => 'testsvr1']);
        $this->assertDatabaseHas('servers', ['id' => 'testsvr1', 'has_yabs' => 1]);
    }

    public function test_new_route_rejects_missing_wrong_and_query_string_tokens(): void
    {
        $this->postJson('/api/yabs/testsvr1', $this->payload())->assertUnauthorized();
        $this->withToken('wrong')->postJson('/api/yabs/testsvr1', $this->payload())->assertUnauthorized();
        $this->flushHeaders();
        $this->postJson('/api/yabs/testsvr1?api_token='.$this->user->api_token, $this->payload())->assertUnauthorized();
        $this->assertDatabaseCount('yabs', 0);
    }

    public function test_legacy_route_remains_compatible_and_can_be_disabled(): void
    {
        $this->postJson('/api/yabs/testsvr1/'.$this->user->api_token, $this->payload())
            ->assertOk()->assertHeader('Deprecation', 'true')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('yabs', 1);
        config(['yabs.allow_legacy_url_tokens' => false]);
        $this->postJson('/api/yabs/testsvr1/'.$this->user->api_token, $this->payload())->assertStatus(410);
        $this->assertDatabaseCount('yabs', 1);
    }

    public function test_bad_yabs_data_does_not_leave_partial_records(): void
    {
        $payload = $this->payload();
        $payload['fio'] = [];
        $this->withToken($this->user->api_token)->postJson('/api/yabs/testsvr1', $payload)->assertStatus(500);
        $this->assertDatabaseCount('yabs', 0);
        $this->assertDatabaseCount('disk_speed', 0);
        $this->assertDatabaseHas('servers', ['id' => 'testsvr1', 'has_yabs' => 0]);
    }

    public function test_server_page_no_longer_places_the_api_token_in_the_yabs_url(): void
    {
        $html = $this->actingAs($this->user)->get(route('servers.show', $this->server))->assertOk()->getContent();
        $this->assertStringNotContainsString('/api/yabs/testsvr1/'.$this->user->api_token, $html);
        $this->assertStringContainsString('/api/yabs/testsvr1', $html);
        $this->assertStringContainsString('curl --config -', $html);
        $this->assertStringContainsString('--data-binary "@$result"', $html);
        $this->assertStringContainsString('MYIDLERS_TOKEN', $html);
    }
}
