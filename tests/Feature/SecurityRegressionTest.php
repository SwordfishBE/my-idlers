<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_limit_cannot_be_bypassed_by_posting_directly(): void
    {
        config(['auth.max_users' => 1]);
        User::factory()->create();
        $this->post('/register', ['name' => 'Unexpected', 'email' => 'other@example.org', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertRedirect('/login');
        $this->assertSame(1, User::count());
        $this->assertGuest();
    }

    public function test_unlimited_registration_remains_available_when_configured(): void
    {
        config(['auth.max_users' => 0]);
        User::factory()->create();
        $this->post('/register', ['name' => 'Allowed', 'email' => 'other@example.org', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertRedirect('/');
        $this->assertSame(2, User::count());
    }

    public function test_operating_system_name_is_escaped_in_icon_html(): void
    {
        $html = Server::osIntToIcon(1, "Linux' onmouseover='alert(1)");
        $this->assertStringNotContainsString("title='Linux' onmouseover=", $html);
        $this->assertStringContainsString('&#039;', $html);
    }
}
