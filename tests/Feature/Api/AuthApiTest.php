<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesDemoRoutine;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use CreatesDemoRoutine;
    use RefreshDatabase;

    public function test_health_endpoint(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('product', 'phoenix');
    }

    public function test_login_returns_token_and_companies(): void
    {
        $this->seed();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@pyro-systems.com',
            'password' => config('phoenix.demo_root_password'),
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user', 'companies']);
    }

    public function test_login_includes_billing_contact_per_tenant(): void
    {
        $this->seed();

        $mein = $this->postJson('/api/v1/auth/login', [
            'email' => 'claudio.rodriguez@mein-company.com',
            'password' => config('phoenix.demo_password'),
        ])->assertOk();

        $mein->assertJsonPath('companies.0.billing_contact_email', 'elena.sanchez@mein-company.com');

        $dom = $this->postJson('/api/v1/auth/login', [
            'email' => 'gilberto-sanchez@dom-g.com',
            'password' => config('phoenix.demo_password'),
        ])->assertOk();

        $dom->assertJsonPath('companies.0.billing_contact_email', 'luis-olvera@dom-g.com');
    }

    public function test_user_can_change_own_password(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'misael.palos@mein-company.com')->firstOrFail();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/password', [
            'current_password' => config('phoenix.demo_password'),
            'password' => 'MiNuevaClave2026',
            'password_confirmation' => 'MiNuevaClave2026',
        ])
            ->assertOk();

        $this->assertTrue(Hash::check('MiNuevaClave2026', $user->fresh()->password));
    }

    public function test_user_cannot_change_password_with_wrong_current_password(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'misael.palos@mein-company.com')->firstOrFail();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/password', [
            'current_password' => 'incorrecta',
            'password' => 'MiNuevaClave2026',
            'password_confirmation' => 'MiNuevaClave2026',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_routines_require_company_header(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/routines')
            ->assertStatus(400);
    }

    public function test_list_routines_with_company_context(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();
        $this->demoRoutine();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/routines')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'assigned');
    }
}
