<?php

namespace Tests\Feature;

use App\Models\IPs;
use App\Models\Locations;
use App\Models\OS;
use App\Models\Pricing;
use App\Models\Providers;
use App\Models\Server;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Server $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        
        $provider = Providers::create(['name' => 'Test Provider']);
        $location = Locations::create(['name' => 'Test Location']);
        $os = OS::create(['name' => 'Ubuntu 22.04']);
        Settings::create(['id' => 1]);

        // Create pricing first
        Pricing::create([
            'service_id' => 'testsvr1',
            'service_type' => 1,
            'currency' => 'USD',
            'price' => 5.00,
            'term' => 1,
            'as_usd' => 5.00,
            'usd_per_month' => 5.00,
            'next_due_date' => now()->addMonth()->format('Y-m-d')
        ]);

        $this->server = Server::create([
            'id' => 'testsvr1',
            'hostname' => 'test-server.example.com',
            'server_type' => 1,
            'os_id' => $os->id,
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'ram' => 2048,
            'disk' => 50,
            'cpu' => 2
        ]);
    }

    public function test_guests_cannot_access_ips_index()
    {
        $response = $this->get(route('IPs.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_ips_index()
    {
        $response = $this->actingAs($this->user)->get(route('IPs.index'));
        $response->assertStatus(200);
        $response->assertViewIs('ips.index');
    }

    public function test_authenticated_user_can_view_create_ip_form()
    {
        $response = $this->actingAs($this->user)->get(route('IPs.create'));
        $response->assertStatus(200);
        $response->assertViewIs('ips.create');
    }

    public function test_authenticated_user_can_create_ip()
    {
        // Mock the HTTP call to ipwhois.app
        \Illuminate\Support\Facades\Http::fake([
            'ipwho.is/*' => \Illuminate\Support\Facades\Http::response([
                'success' => true,
                'ip' => '192.168.1.100',
                'continent' => 'North America',
                'country' => 'United States',
                'region' => 'California',
                'city' => 'Los Angeles',
                'connection' => ['org' => 'Test Org', 'isp' => 'Test ISP', 'asn' => 12345],
                'timezone' => ['utc' => '-08:00']
            ], 200)
        ]);

        $response = $this->actingAs($this->user)->post(route('IPs.store'), [
            'address' => '192.168.1.100',
            'ip_type' => 'ipv4',
            'service_id' => $this->server->id
        ]);

        $response->assertRedirect(route('IPs.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('ips', ['address' => '192.168.1.100']);
    }

    public function test_ip_address_is_required()
    {
        $response = $this->actingAs($this->user)->post(route('IPs.store'), [
            'address' => '',
            'ip_type' => 'ipv4',
            'service_id' => $this->server->id
        ]);

        $response->assertSessionHasErrors('address');
    }

    public function test_ip_address_must_be_valid()
    {
        $response = $this->actingAs($this->user)->post(route('IPs.store'), [
            'address' => 'invalid-ip',
            'ip_type' => 'ipv4',
            'service_id' => $this->server->id
        ]);

        $response->assertSessionHasErrors('address');
    }

    public function test_adding_ipv6_from_server_page_refreshes_cached_details()
    {
        \Illuminate\Support\Facades\Http::fake(['ipwho.is/*' => \Illuminate\Support\Facades\Http::response([], 503)]);
        $details = route('servers.show', $this->server);
        $this->actingAs($this->user)->get($details)->assertOk()->assertDontSee('2001:db8::42');
        $this->get(route('IPs.create', ['server' => $this->server->id]))
            ->assertOk()->assertSee('value="'.$this->server->id.'" selected', false);
        $this->post(route('IPs.store'), [
            'address' => '2001:db8::42',
            'service_id' => $this->server->id,
            'return_to_server' => '1',
        ])->assertRedirect($details);
        $this->assertDatabaseHas('ips', ['address' => '2001:db8::42', 'is_ipv4' => 0, 'service_id' => $this->server->id]);
        $this->get($details)->assertOk()->assertSee('2001:db8::42');
    }

    public function test_lookup_saves_ipv6_and_preserves_information_on_rate_limit(): void
    {
        $ip = IPs::create(['id' => 'testip02', 'service_id' => $this->server->id, 'address' => '2001:4860:4860::8888', 'is_ipv4' => 0, 'active' => 1]);
        \Illuminate\Support\Facades\Http::fake(['ipwho.is/*' => \Illuminate\Support\Facades\Http::sequence()
            ->push(['success' => true, 'ip' => '2001:4860:4860::8888', 'country' => 'United States', 'connection' => ['asn' => 15169, 'isp' => 'Google'], 'timezone' => ['utc' => '-08:00'], 'latitude' => 37.3, 'longitude' => -121.8])
            ->push(['success' => false, 'message' => 'Rate limit exceeded'], 429)]);
        $this->assertTrue(IPs::getUpdateIpInfo($ip));
        $this->assertDatabaseHas('ips', ['id' => $ip->id, 'country' => 'United States', 'asn' => 'AS15169', 'isp' => 'Google']);
        $this->assertFalse(IPs::getUpdateIpInfo($ip));
        $this->assertDatabaseHas('ips', ['id' => $ip->id, 'country' => 'United States', 'asn' => 'AS15169']);
    }

    public function test_service_id_is_required()
    {
        $response = $this->actingAs($this->user)->post(route('IPs.store'), [
            'address' => '192.168.1.100',
            'ip_type' => 'ipv4',
            'service_id' => ''
        ]);

        $response->assertSessionHasErrors('service_id');
    }

    public function test_authenticated_user_can_delete_ip()
    {
        $ip = IPs::create([
            'id' => 'testip01',
            'service_id' => $this->server->id,
            'address' => '192.168.1.100',
            'is_ipv4' => 1,
            'active' => 1
        ]);

        $response = $this->actingAs($this->user)->delete(route('IPs.destroy', $ip));

        $response->assertRedirect(route('IPs.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('ips', ['id' => 'testip01']);
    }
}
