<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformTenantApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_list_and_create_tenant(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/platform/tenants')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Mein Company']);

        $this->withToken($token)
            ->postJson('/api/v1/platform/tenants', [
                'name' => 'Acme Ops',
                'admin_name' => 'Acme Admin',
                'admin_email' => 'admin@acme.example',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Acme Ops')
            ->assertJsonPath('data.admin_user_id', fn ($id) => is_int($id) && $id > 0);

        $this->assertTrue(
            Company::query()->where('name', 'Mein Company')->exists(),
        );
    }

    public function test_platform_admin_assume_records_audit(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $company = Company::query()->where('name', 'Dom-G')->firstOrFail();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/v1/platform/tenants/{$company->id}/assume")
            ->assertOk();

        $this->assertDatabaseHas('audit_entries', [
            'action' => 'platform.tenant_assumed',
            'subject_id' => $company->id,
        ]);
    }

    public function test_tenant_admin_cannot_access_platform_tenants(): void
    {
        $this->seed();

        $tenantAdmin = User::query()->where('email', 'emilio.sanchez@mein-company.com')->firstOrFail();
        $company = Company::query()->where('name', 'Mein Company')->firstOrFail();
        $token = $tenantAdmin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/platform/tenants')
            ->assertForbidden();
    }

    public function test_platform_admin_login_lists_assumed_tenants_without_membership(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $this->assertTrue($admin->is_platform_admin);
        $this->assertFalse(
            $admin->memberships()->whereHas('company', fn ($q) => $q->where('name', 'Mein Company'))->exists(),
        );

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@pyro-systems.com',
            'password' => config('phoenix.demo_root_password'),
            'device_name' => 'test',
        ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Mein Company', 'assumed' => true])
            ->assertJsonFragment(['name' => 'Dom-G', 'assumed' => true]);
    }

    public function test_inactive_tenant_blocked_for_tenant_user_but_not_platform_admin(): void
    {
        $this->seed();

        $company = Company::query()->where('name', 'Mein Company')->firstOrFail();
        $company->update(['is_active' => false]);

        $tenantAdmin = User::query()->where('email', 'emilio.sanchez@mein-company.com')->firstOrFail();
        Sanctum::actingAs($tenantAdmin);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/routines')
            ->assertForbidden();

        $platform = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        Sanctum::actingAs($platform);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/routines')
            ->assertOk();
    }

    public function test_platform_admin_can_update_tenant(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $company = Company::query()->where('name', 'Dom-G')->firstOrFail();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson("/api/v1/platform/tenants/{$company->id}", [
                'name' => 'Dom-G Servicios',
                'legal_name' => 'Dom-G Servicios S.A. de C.V.',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Dom-G Servicios')
            ->assertJsonPath('data.legal_name', 'Dom-G Servicios S.A. de C.V.');

        $company->refresh();
        $this->assertSame('Dom-G Servicios', $company->name);
    }

    public function test_platform_admin_can_update_tenant_admin_and_logo(): void
    {
        Storage::fake('public');
        $this->seed();

        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $company = Company::query()->where('name', 'Mein Company')->firstOrFail();
        $tenantAdmin = User::query()->where('email', 'emilio.sanchez@mein-company.com')->firstOrFail();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson("/api/v1/platform/tenants/{$company->id}", [
                'admin_name' => 'Emilio Sánchez Actualizado',
                'admin_email' => 'emilio.sanchez@mein-company.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.admin_name', 'Emilio Sánchez Actualizado');

        $tenantAdmin->refresh();
        $this->assertSame('Emilio Sánchez Actualizado', $tenantAdmin->name);

        $file = UploadedFile::fake()->image('logo.png', 120, 120);

        $this->withToken($token)
            ->postJson("/api/v1/platform/tenants/{$company->id}/logo", [
                'logo' => $file,
            ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['logo_url']]);

        $company->refresh();
        $this->assertNotNull($company->logo_path);
        Storage::disk('public')->assertExists($company->logo_path);
    }

    public function test_platform_admin_can_upload_user_avatar(): void
    {
        Storage::fake('public');
        $this->seed();

        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $tenantUser = User::query()->where('email', 'emilio.sanchez@mein-company.com')->firstOrFail();
        $token = $admin->createToken('test')->plainTextToken;

        $file = UploadedFile::fake()->image('avatar.png', 128, 128);

        $this->withToken($token)
            ->postJson("/api/v1/platform/users/{$tenantUser->id}/avatar", [
                'avatar' => $file,
            ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['user_id', 'avatar_url']]);

        $tenantUser->refresh();
        $this->assertNotNull($tenantUser->avatar_path);
        Storage::disk('public')->assertExists($tenantUser->avatar_path);
    }
}
