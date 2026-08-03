<?php

namespace Tests\Feature\Api;

use App\Enums\MembershipRole;
use App\Models\User;
use App\Services\Identity\CompanyAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\UsesMeinCompany;
use Tests\TestCase;

class CompanyUsersApiTest extends TestCase
{
    use RefreshDatabase;
    use UsesMeinCompany;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(CompanyAuthorizationService::class)->bootstrapAllCompanies();
    }

    public function test_admin_can_list_company_users(): void
    {
        $company = $this->meinCompany();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        Sanctum::actingAs($admin);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/company/users')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'email', 'role', 'is_active', 'avatar_url']]]);
    }

    public function test_technician_without_clients_permission_cannot_list_clients(): void
    {
        $company = $this->meinCompany();
        $technician = User::query()->where('email', 'misael.palos@mein-company.com')->first();
        Sanctum::actingAs($technician);

        $me = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->json('companies.0.modules');

        $this->assertFalse($me['clients']['visible']);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/clients')
            ->assertForbidden();
    }

    public function test_modules_payload_is_rejected(): void
    {
        $company = $this->meinCompany();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $technician = User::query()->where('email', 'misael.palos@mein-company.com')->first();
        Sanctum::actingAs($admin);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson('/api/v1/company/users/'.$technician->id, [
                'modules' => ['clients' => ['read' => true, 'write' => false]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['modules']);
    }

    public function test_supervisor_cannot_manage_users(): void
    {
        $company = $this->meinCompany();
        $supervisor = User::query()->where('email', 'claudio.rodriguez@mein-company.com')->first();
        Sanctum::actingAs($supervisor);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/company/users')
            ->assertForbidden();
    }

    public function test_admin_can_grant_extra_permission_to_technician(): void
    {
        $company = $this->meinCompany();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $technician = User::query()->where('email', 'misael.palos@mein-company.com')->first();
        Sanctum::actingAs($admin);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson('/api/v1/company/users/'.$technician->id, [
                'extra_permissions' => ['clients.view'],
            ])
            ->assertOk()
            ->assertJsonPath('data.extra_permissions', ['clients.view']);

        Sanctum::actingAs($technician);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/clients')
            ->assertOk();
    }

    public function test_cannot_grant_users_manage_to_non_administrator(): void
    {
        $company = $this->meinCompany();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $technician = User::query()->where('email', 'misael.palos@mein-company.com')->first();
        Sanctum::actingAs($admin);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson('/api/v1/company/users/'.$technician->id, [
                'extra_permissions' => ['company.users.manage'],
            ])
            ->assertUnprocessable();
    }

    public function test_admin_can_change_user_role(): void
    {
        $company = $this->meinCompany();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $technician = User::query()->where('email', 'misael.palos@mein-company.com')->first();
        Sanctum::actingAs($admin);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson('/api/v1/company/users/'.$technician->id, [
                'role' => MembershipRole::Supervisor->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.role', MembershipRole::Supervisor->value);

        $this->assertDatabaseHas('company_memberships', [
            'company_id' => $company->id,
            'user_id' => $technician->id,
            'role' => MembershipRole::Supervisor->value,
        ]);
    }

    public function test_admin_can_add_user_by_email(): void
    {
        $company = $this->meinCompany();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        Sanctum::actingAs($admin);
        Mail::fake();

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/company/users', [
                'email' => 'nuevo@pyro-systems.com',
                'name' => 'Usuario Nuevo',
                'role' => MembershipRole::Technician->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'nuevo@pyro-systems.com')
            ->assertJsonStructure(['generated_password']);

        $user = User::query()->where('email', 'nuevo@pyro-systems.com')->first();
        $this->assertNotNull($user);
        $this->assertDatabaseHas('company_memberships', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => MembershipRole::Technician->value,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_add_user_with_custom_password(): void
    {
        $company = $this->meinCompany();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        Sanctum::actingAs($admin);
        Mail::fake();

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/company/users', [
                'email' => 'con-clave@pyro-systems.com',
                'name' => 'Usuario con clave',
                'role' => MembershipRole::Technician->value,
                'password' => 'ClaveSegura2026',
                'password_confirmation' => 'ClaveSegura2026',
                'send_invitation' => false,
            ])
            ->assertCreated()
            ->assertJsonMissing(['generated_password']);

        $user = User::query()->where('email', 'con-clave@pyro-systems.com')->firstOrFail();
        $this->assertTrue(Hash::check('ClaveSegura2026', $user->password));
        Mail::assertNothingSent();
    }

    public function test_admin_can_reset_user_password(): void
    {
        $company = $this->meinCompany();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $technician = User::query()->where('email', 'misael.palos@mein-company.com')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson('/api/v1/company/users/'.$technician->id, [
                'password' => 'NuevaClave2026',
                'password_confirmation' => 'NuevaClave2026',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('NuevaClave2026', $technician->fresh()->password));
    }

    public function test_admin_cannot_reset_own_password_via_company_users(): void
    {
        $company = $this->meinCompany();
        $admin = User::query()->where('email', 'emilio.sanchez@mein-company.com')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson('/api/v1/company/users/'.$admin->id, [
                'password' => 'NuevaClave2026',
                'password_confirmation' => 'NuevaClave2026',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_login_includes_permissions_for_company(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@pyro-systems.com',
            'password' => config('phoenix.demo_root_password'),
        ])
            ->assertOk()
            ->assertJsonStructure(['companies' => [['permissions']]]);
    }
}
