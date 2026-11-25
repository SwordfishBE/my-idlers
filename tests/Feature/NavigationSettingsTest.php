<?php

namespace Tests\Feature;

use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_settings_are_saved_and_apply_after_cache_refresh(): void
    {
        Settings::create(['id' => 1]);
        $this->actingAs(User::factory()->create());
        $this->get(route('settings.index'))->assertOk()->assertSee('href="'.route('shared.index').'"', false);
        $this->put(route('settings.navigation.update'), [
            'nav_servers' => 1, 'nav_shared' => 0, 'nav_reseller' => 0, 'nav_domains' => 1,
        ])->assertRedirect(route('settings.index'));
        $this->assertDatabaseHas('settings', ['id' => 1, 'nav_shared' => 0, 'nav_reseller' => 0]);
        $this->get(route('settings.index'))->assertOk()
            ->assertDontSee('href="'.route('shared.index').'"', false)
            ->assertDontSee('href="'.route('reseller.index').'"', false)
            ->assertSee('href="'.route('servers.index').'"', false)
            ->assertSee('href="'.route('domains.index').'"', false)
            ->assertSee('href="'.route('labels.index').'"', false);
    }
}
