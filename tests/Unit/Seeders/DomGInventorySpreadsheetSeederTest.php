<?php

namespace Tests\Unit\Seeders;

use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplyItem;
use App\Models\SupplyType;
use Database\Seeders\Support\DomGInventorySpreadsheetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomGInventorySpreadsheetSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_maps_spreadsheet_rows_and_seeds_inventory(): void
    {
        $company = Company::query()->create(['name' => 'Dom-G']);
        $supplier = Supplier::query()->create([
            'company_id' => $company->id,
            'code' => 'PROV-DOMG',
            'name' => 'Proveedor Dom-G',
        ]);

        app(DomGInventorySpreadsheetSeeder::class)->seed(
            $company,
            $supplier,
            DomGInventorySpreadsheetSeeder::defaultSpreadsheetPath(),
        );

        $this->assertSame(25, SupplyItem::query()->withoutGlobalScopes()->where('company_id', $company->id)->count());
        $this->assertTrue(
            SupplyType::query()->where('company_id', $company->id)->where('code', 'sublimacion')->exists()
        );

        $taza = SupplyItem::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('sku', 'SUB-TAZA-BL')
            ->first();

        $this->assertNotNull($taza);
        $this->assertSame('Taza Blanca 11oz para Sublimación', $taza->name);
        $this->assertSame('consumable', $taza->sector);
        $this->assertSame('pza', $taza->unit);
        $this->assertEquals(120.0, (float) $taza->quantity_on_hand);
        $this->assertSame('Estante A1', $taza->storage_location);
    }

    public function test_parses_existencia_and_estado_helpers(): void
    {
        $this->assertSame(
            ['quantity' => 12.0, 'unit' => 'pqt'],
            DomGInventorySpreadsheetSeeder::parseExistencia('12 cono'),
        );

        $this->assertSame(
            ['is_active' => false, 'low_stock' => false],
            DomGInventorySpreadsheetSeeder::parseEstado('Agotado'),
        );

        $this->assertSame(
            ['is_active' => true, 'low_stock' => true],
            DomGInventorySpreadsheetSeeder::parseEstado('Bajo'),
        );
    }
}
