<?php

namespace Tests\Feature\Api;

use App\Enums\MembershipRole;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Services\Identity\CompanyAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyUsersApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(CompanyAuthorizationService::class)->bootstrapAllCompanies();
    }

    public function test_admin_can_list_company_users(): void
    {
        $company = Company::query()->first();
        $admin = User::query()->where('email', 'admin@noah.local')->first();
        Sanctum::actingAs($admin);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/company/users')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'email', 'role', 'is_active']]]);
    }

    public function test_technician_without_clients_permission_cannot_list_clients(): void
    {
        $company = Company::query()->first();
        $technician = User::query()->where('email', 'tecnico@noah.local')->first();
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
        $company = Company::query()->first();
        $admin = User::query()->where('email', 'admin@noah.local')->first();
        $technician = User::query()->where('email', 'tecnico@noah.local')->first();
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
        $company = Company::query()->first();
        $supervisor = User::query()->where('email', 'supervisor@noah.local')->first();
        Sanctum::actingAs($supervisor);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/company/users')
            ->assertForbidden();
    }

    public function test_admin_can_grant_extra_permission_to_technician(): void
    {
        $company = Company::query()->first();
        $admin = User::query()->where('email', 'admin@noah.local')->first();
        $technician = User::query()->where('email', 'tecnico@noah.local')->first();
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
        $company = Company::query()->first();
        $admin = User::query()->where('email', 'admin@noah.local')->first();
        $technician = User::query()->where('email', 'tecnico@noah.local')->first();
        Sanctum::actingAs($admin);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson('/api/v1/company/users/'.$technician->id, [
                'extra_permissions' => ['company.users.manage'],
            ])
            ->assertUnprocessable();
    }

    public function test_admin_can_change_user_role(): void
    {
        $company = Company::query()->first();
        $admin = User::query()->where('email', 'admin@noah.local')->first();
        $technician = User::query()->where('email', 'tecnico@noah.local')->first();
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
        $company = Company::query()->first();
        $admin = User::query()->where('email', 'admin@noah.local')->first();
        Sanctum::actingAs($admin);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/company/users', [
                'email' => 'nuevo@noah.local',
                'name' => 'Usuario Nuevo',
                'role' => MembershipRole::Technician->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'nuevo@noah.local');

        $user = User::query()->where('email', 'nuevo@noah.local')->first();
        $this->assertNotNull($user);
        $this->assertDatabaseHas('company_memberships', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => MembershipRole::Technician->value,
            'is_active' => true,
        ]);
    }

    public function test_login_includes_permissions_for_company(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@noah.local',
            'password' => config('noah.demo_password'),
        ])
            ->assertOk()
            ->assertJsonStructure(['companies' => [['permissions']]]);
    }
}
