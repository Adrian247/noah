<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use App\Services\Identity\CompanyAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuppliesModuleAccessApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(CompanyAuthorizationService::class)->bootstrapAllCompanies();
    }

    public function test_technician_with_assets_read_only_cannot_create_asset(): void
    {
        $company = Company::query()->first();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $technician = User::query()->where('email', 'technician@sandbox-demo.com')->first();
        Sanctum::actingAs($admin);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson('/api/v1/company/users/'.$technician->id, [
                'extra_permissions' => ['assets.view'],
            ])
            ->assertOk();

        Sanctum::actingAs($technician);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/assets')
            ->assertOk();

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/assets', [
                'site_id' => 1,
                'tag' => 'TAG-RO',
            ])
            ->assertForbidden();
    }

    public function test_technician_with_catalog_items_read_only_cannot_create_item(): void
    {
        $company = Company::query()->first();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $technician = User::query()->where('email', 'technician@sandbox-demo.com')->first();
        Sanctum::actingAs($admin);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson('/api/v1/company/users/'.$technician->id, [
                'extra_permissions' => ['catalog.view'],
            ])
            ->assertOk();

        Sanctum::actingAs($technician);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/catalog/items')
            ->assertOk();

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/catalog/items', [
                'code' => 'EQ-RO',
                'name' => 'Solo lectura',
            ])
            ->assertForbidden();
    }

    public function test_technician_with_supplies_read_only_cannot_create_supply(): void
    {
        $company = Company::query()->first();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $technician = User::query()->where('email', 'technician@sandbox-demo.com')->first();
        Sanctum::actingAs($admin);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson('/api/v1/company/users/'.$technician->id, [
                'extra_permissions' => ['catalog.view'],
            ])
            ->assertOk();

        Sanctum::actingAs($technician);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/inventory/supplies')
            ->assertOk();

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/inventory/supplies', [
                'sku' => 'INS-99',
                'name' => 'Filtro demo',
            ])
            ->assertForbidden();
    }
}
