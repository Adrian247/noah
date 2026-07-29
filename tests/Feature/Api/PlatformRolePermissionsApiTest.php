<?php

namespace Tests\Feature\Api;

use App\Enums\PhoenixPermission;
use App\Models\Company;
use App\Models\User;
use App\Services\Identity\CompanyAuthorizationService;
use App\Services\Identity\RolePermissionTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformRolePermissionsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(CompanyAuthorizationService::class)->bootstrapAllCompanies();
    }

    public function test_platform_admin_can_update_supervisor_role(): void
    {
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        Sanctum::actingAs($admin);

        $templates = app(RolePermissionTemplateService::class);
        $map = $templates->map();
        $map['supervisor'][] = PhoenixPermission::ClientsView->value;
        $map['supervisor'] = array_values(array_unique($map['supervisor']));

        $this->putJson('/api/v1/platform/role-permissions', ['roles' => $map])
            ->assertOk()
            ->assertJsonPath('data.roles.1.name', 'supervisor');

        $fresh = $templates->map();
        $this->assertContains(PhoenixPermission::ClientsView->value, $fresh['supervisor']);
    }

    public function test_non_platform_user_cannot_access(): void
    {
        $supervisor = User::query()->where('email', 'claudio.rodriguez@mein-company.com')->firstOrFail();
        Sanctum::actingAs($supervisor);

        $this->getJson('/api/v1/platform/role-permissions')->assertForbidden();
    }

    public function test_me_includes_platform_admin_flag_for_demo_admin(): void
    {
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.is_platform_admin', true);
    }

    public function test_company_admin_sees_updated_role_permissions(): void
    {
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $company = Company::query()->firstOrFail();
        Sanctum::actingAs($admin);

        $templates = app(RolePermissionTemplateService::class);
        $map = $templates->map();
        $map['technician'][] = PhoenixPermission::AssetsView->value;
        $map['technician'] = array_values(array_unique($map['technician']));

        $this->putJson('/api/v1/platform/role-permissions', ['roles' => $map])->assertOk();

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/company/roles')
            ->assertOk();

        $roles = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/company/roles')
            ->json('data');

        $technician = collect($roles)->firstWhere('name', 'technician');
        $this->assertContains(PhoenixPermission::AssetsView->value, $technician['permissions']);
    }
}
