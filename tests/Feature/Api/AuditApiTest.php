<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_creates_audit_entry_and_admin_can_list_company_audit(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@noah.local')->first();
        $company = Company::query()->first();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@noah.local',
            'password' => config('noah.demo_password'),
        ])->assertOk();

        $this->assertDatabaseHas('audit_entries', [
            'action' => 'auth.login',
            'actor_user_id' => $admin->id,
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/audit/entries')
            ->assertOk();
    }
}
