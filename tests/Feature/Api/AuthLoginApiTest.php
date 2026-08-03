<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLoginApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_admin_can_login_after_refresh_demo(): void
    {
        $this->artisan('phoenix:refresh-demo', ['--skip-migrate' => true])->assertSuccessful();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@sandbox-demo.com',
            'password' => 'pyro.2026$',
            'device_name' => 'test',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email'], 'companies'])
            ->assertJsonPath('user.email', 'admin@sandbox-demo.com');
    }

    public function test_health_reports_demo_accounts_in_local(): void
    {
        $this->artisan('phoenix:refresh-demo', ['--skip-migrate' => true])->assertSuccessful();

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('demo.accounts_ready', true)
            ->assertJsonPath('demo.default_login_email', 'admin@sandbox-demo.com')
            ->assertJsonPath('demo.password', 'pyro.2026$');
    }
}
