<?php

namespace Tests\Feature\Api;

use App\Models\CatalogItem;
use App\Models\Company;
use App\Models\EquipmentType;
use App\Models\SupplyItem;
use App\Models\SupplyType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentSupplyTypesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_equipment_type_crud_and_delete_blocked_when_in_use(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@noah.local')->first();
        $company = Company::query()->first();
        $token = $admin->createToken('test')->plainTextToken;
        $headers = ['X-Company-Id' => (string) $company->id];

        $this->withToken($token)
            ->withHeaders($headers)
            ->postJson('/api/v1/catalog/equipment-types', [
                'code' => 'compresor',
                'name' => 'Compresores',
                'sort_order' => 10,
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'compresor');

        $type = EquipmentType::query()->where('code', 'compresor')->firstOrFail();

        $this->withToken($token)
            ->withHeaders($headers)
            ->getJson('/api/v1/catalog/equipment-types')
            ->assertOk()
            ->assertJsonFragment(['code' => 'vehiculo']);

        CatalogItem::query()->create([
            'company_id' => $company->id,
            'equipment_type_id' => $type->id,
            'code' => 'CMP-01',
            'name' => 'Compresor demo',
        ]);

        $this->withToken($token)
            ->withHeaders($headers)
            ->deleteJson('/api/v1/catalog/equipment-types/'.$type->id)
            ->assertStatus(422);

        $this->withToken($token)
            ->withHeaders($headers)
            ->putJson('/api/v1/catalog/equipment-types/'.$type->id, ['name' => 'Compresores industriales'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Compresores industriales');
    }

    public function test_supply_type_crud(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@noah.local')->first();
        $company = Company::query()->first();
        $token = $admin->createToken('test')->plainTextToken;
        $headers = ['X-Company-Id' => (string) $company->id];

        $this->withToken($token)
            ->withHeaders($headers)
            ->postJson('/api/v1/catalog/supply-types', [
                'code' => 'lubricantes',
                'name' => 'Lubricantes',
            ])
            ->assertCreated();

        $type = SupplyType::query()->where('code', 'lubricantes')->firstOrFail();

        $this->withToken($token)
            ->withHeaders($headers)
            ->postJson('/api/v1/inventory/supplies', [
                'supply_type_id' => $type->id,
                'sku' => 'LUB-01',
                'name' => 'Aceite sintético',
            ])
            ->assertCreated()
            ->assertJsonPath('data.supply_type.code', 'lubricantes');

        $this->withToken($token)
            ->withHeaders($headers)
            ->deleteJson('/api/v1/catalog/supply-types/'.$type->id)
            ->assertStatus(422);

        SupplyItem::query()->where('sku', 'LUB-01')->delete();

        $this->withToken($token)
            ->withHeaders($headers)
            ->deleteJson('/api/v1/catalog/supply-types/'.$type->id)
            ->assertNoContent();
    }
}
