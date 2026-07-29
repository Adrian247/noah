<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\SupplyItem;
use App\Models\SupplyType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventorySuppliesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_supply_item_stock_crud_and_movements(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $company = Company::query()->firstOrFail();
        $token = $admin->createToken('test')->plainTextToken;
        $headers = ['X-Company-Id' => (string) $company->id];

        $this->withToken($token)
            ->withHeaders($headers)
            ->getJson('/api/v1/inventory/meta')
            ->assertOk()
            ->assertJsonPath('data.sectors.0.value', 'industrial');

        $type = SupplyType::query()->create([
            'company_id' => $company->id,
            'code' => 'lubricantes-test',
            'name' => 'Lubricantes test',
        ]);

        $this->withToken($token)
            ->withHeaders($headers)
            ->postJson('/api/v1/inventory/supplies', [
                'supply_type_id' => $type->id,
                'sku' => 'LUB-TEST-5W30',
                'name' => 'Aceite motor 5W-30',
                'sector' => 'mechanical',
                'material_kind' => 'chemical',
                'unit' => 'lt',
                'min_stock' => 10,
                'storage_location' => 'Bodega A-2',
                'opening_quantity' => 24,
            ])
            ->assertCreated()
            ->assertJsonPath('data.sku', 'LUB-TEST-5W30');

        $item = SupplyItem::query()->where('sku', 'LUB-TEST-5W30')->firstOrFail();
        $this->assertSame('24.0000', (string) $item->quantity_on_hand);

        $this->withToken($token)
            ->withHeaders($headers)
            ->postJson('/api/v1/inventory/supplies/'.$item->id.'/movements', [
                'movement_type' => 'out',
                'quantity' => 4,
                'reference' => 'RUT-1001',
            ])
            ->assertCreated()
            ->assertJsonPath('supply_item.quantity_on_hand', '20.0000');

        $this->withToken($token)
            ->withHeaders($headers)
            ->getJson('/api/v1/inventory/supplies?low_stock=1&q=LUB-TEST-5W30')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withToken($token)
            ->withHeaders($headers)
            ->putJson('/api/v1/inventory/supplies/'.$item->id, [
                'min_stock' => 22,
            ])
            ->assertOk();

        $this->withToken($token)
            ->withHeaders($headers)
            ->getJson('/api/v1/inventory/supplies?low_stock=1&q=LUB-TEST-5W30')
            ->assertOk()
            ->assertJsonPath('data.0.sku', 'LUB-TEST-5W30');
    }

    public function test_supply_import_creates_and_updates_by_sku(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $company = Company::query()->firstOrFail();
        $token = $admin->createToken('test')->plainTextToken;
        $headers = ['X-Company-Id' => (string) $company->id];

        $type = SupplyType::query()->create([
            'company_id' => $company->id,
            'code' => 'import-test',
            'name' => 'Import test',
        ]);

        $this->withToken($token)
            ->withHeaders($headers)
            ->postJson('/api/v1/inventory/supplies/import', [
                'rows' => [
                    [
                        'sku' => 'IMP-001',
                        'name' => 'Artículo importado',
                        'supply_type_code' => 'import-test',
                        'quantity_on_hand' => 5,
                        'storage_location' => 'Estante 1',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.created', 1);

        $item = SupplyItem::query()->where('sku', 'IMP-001')->firstOrFail();
        $this->assertSame('5.0000', (string) $item->quantity_on_hand);

        $this->withToken($token)
            ->withHeaders($headers)
            ->postJson('/api/v1/inventory/supplies/import', [
                'rows' => [
                    [
                        'sku' => 'IMP-001',
                        'name' => 'Artículo importado (editado)',
                        'supply_type_code' => 'import-test',
                        'quantity_on_hand' => 8,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.updated', 1);

        $item->refresh();
        $this->assertSame('Artículo importado (editado)', $item->name);
        $this->assertSame('8.0000', (string) $item->quantity_on_hand);
    }
}
