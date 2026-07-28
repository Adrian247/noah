<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertJsonPath('product', 'noah');
    }

    public function test_login_returns_token_and_companies(): void
    {
        $this->seed();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@noah.local',
            'password' => config('noah.demo_password'),
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user', 'companies']);
    }

    public function test_routines_require_company_header(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@noah.local')->first();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/routines')
            ->assertStatus(400);
    }

    public function test_list_routines_with_company_context(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@noah.local')->first();
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
