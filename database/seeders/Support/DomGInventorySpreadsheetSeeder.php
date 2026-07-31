<?php

namespace Database\Seeders\Support;

use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplyItem;
use App\Models\SupplyType;
use App\Services\Inventory\InventoryStockService;
use App\Support\Spreadsheet\SimpleXlsxReader;
use App\Support\SupplyUnits;
use Illuminate\Support\Str;

/**
 * Carga el inventario operativo demo de Dom-G desde la hoja Excel de promocionales/textil.
 */
final class DomGInventorySpreadsheetSeeder
{
    private const LEGACY_AUTOMOTIVE_SKUS = [
        'FIL-1230A153-OEM',
        'FIL-AIR-2030515-SAK',
        'FRE-P54038-BRM',
        'SUS-AMORT-GROB-PAR',
        'FLT-AIR-01',
        'LUB-5W30',
        'EPP-GLOVE',
        'FIL-ACE-PREM',
    ];

    /**
     * @var array<string, array{code: string, name: string, sort_order: int}>
     */
    private const SUPPLY_TYPES = [
        'sublimacion' => ['code' => 'sublimacion', 'name' => 'Sublimación', 'sort_order' => 10],
        'bordado' => ['code' => 'bordado', 'name' => 'Bordado', 'sort_order' => 11],
        'insumo' => ['code' => 'insumo', 'name' => 'Insumo', 'sort_order' => 12],
    ];

    public function __construct(
        private readonly InventoryStockService $stock,
    ) {}

    public static function defaultSpreadsheetPath(): string
    {
        $override = env('PHOENIX_DEMO_DOMG_INVENTORY_XLSX');
        if (is_string($override) && $override !== '' && is_file($override)) {
            return $override;
        }

        return database_path('seeders/data/dom-g-inventory.xlsx');
    }

    public function seed(Company $company, Supplier $supplier, ?string $path = null): void
    {
        $path ??= self::defaultSpreadsheetPath();
        if (! is_file($path)) {
            throw new \RuntimeException("No se encontró el inventario Dom-G en: {$path}");
        }

        $this->removeLegacyAutomotiveItems($company);
        $typesByCode = $this->ensureSupplyTypes($company);

        foreach (SimpleXlsxReader::sheetToAssocRows($path) as $row) {
            $this->seedRow($company, $supplier, $typesByCode, $row);
        }
    }

    private function removeLegacyAutomotiveItems(Company $company): void
    {
        SupplyItem::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('sku', self::LEGACY_AUTOMOTIVE_SKUS)
            ->delete();
    }

    /**
     * @return array<string, SupplyType>
     */
    private function ensureSupplyTypes(Company $company): array
    {
        $typesByCode = [];

        foreach (self::SUPPLY_TYPES as $definition) {
            $typesByCode[$definition['code']] = SupplyType::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'sort_order' => $definition['sort_order'],
                ],
            );
        }

        return $typesByCode;
    }

    /**
     * @param  array<string, SupplyType>  $typesByCode
     * @param  array<string, string>  $row
     */
    private function seedRow(Company $company, Supplier $supplier, array $typesByCode, array $row): void
    {
        $sku = trim($row['SKU'] ?? $row['sku'] ?? '');
        $name = trim($row['Nombre'] ?? $row['name'] ?? '');
        if ($sku === '' || $name === '') {
            return;
        }

        $tipoLabel = trim($row['Tipo'] ?? $row['tipo'] ?? '');
        $sectorLabel = trim($row['Sector'] ?? $row['sector'] ?? '');
        $existencia = trim($row['Existencia'] ?? $row['existencia'] ?? '');
        $ubicacion = trim($row['Ubicación'] ?? $row['Ubicacion'] ?? $row['ubicacion'] ?? '');
        $estado = trim($row['Estado'] ?? $row['estado'] ?? '');

        $typeCode = self::supplyTypeCodeFromLabel($tipoLabel);
        $supplyType = $typesByCode[$typeCode] ?? $typesByCode['insumo'];
        ['quantity' => $quantity, 'unit' => $unit] = self::parseExistencia($existencia);
        ['is_active' => $isActive, 'low_stock' => $lowStock] = self::parseEstado($estado);

        $minStock = null;
        if ($lowStock && $quantity > 0) {
            $minStock = $quantity + 1;
        }

        $item = SupplyItem::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'sku' => $sku,
            ],
            [
                'supply_type_id' => $supplyType->id,
                'supplier_id' => $supplier->id,
                'name' => $name,
                'sector' => self::mapSector($sectorLabel),
                'material_kind' => self::mapMaterialKind($tipoLabel, $sectorLabel),
                'unit' => $unit,
                'storage_location' => $ubicacion !== '' ? $ubicacion : null,
                'is_active' => $isActive,
                'min_stock' => $minStock,
                'specifications' => array_filter([
                    'tipo_operativo' => $tipoLabel !== '' ? $tipoLabel : null,
                    'sector_operativo' => $sectorLabel !== '' ? $sectorLabel : null,
                    'estado_operativo' => $estado !== '' ? $estado : null,
                ]),
            ],
        );

        $this->syncQuantity($item, $quantity);
    }

    private function syncQuantity(SupplyItem $item, float $targetQuantity): void
    {
        $current = (float) $item->fresh()->quantity_on_hand;
        if ($targetQuantity === $current) {
            return;
        }

        if ($targetQuantity > $current) {
            $this->stock->recordMovement($item, [
                'movement_type' => 'in',
                'quantity' => $targetQuantity - $current,
                'reference' => 'dom-g-demo-seed',
                'notes' => 'Inventario demo Dom-G (Excel)',
            ]);

            return;
        }

        $this->stock->recordMovement($item, [
            'movement_type' => 'out',
            'quantity' => $current - $targetQuantity,
            'reference' => 'dom-g-demo-seed',
            'notes' => 'Inventario demo Dom-G (Excel)',
        ]);
    }

    public static function supplyTypeCodeFromLabel(string $label): string
    {
        $normalized = Str::lower(Str::ascii(trim($label)));

        return match ($normalized) {
            'sublimacion' => 'sublimacion',
            'bordado' => 'bordado',
            'insumo' => 'insumo',
            default => 'insumo',
        };
    }

    public static function mapSector(string $label): string
    {
        $normalized = Str::lower(Str::ascii(trim($label)));

        return match ($normalized) {
            'promocional', 'textil', 'accesorios' => 'consumable',
            'bordado', 'sublimacion' => 'other',
            default => 'other',
        };
    }

    public static function mapMaterialKind(string $tipoLabel, string $sectorLabel): string
    {
        $tipo = Str::lower(Str::ascii(trim($tipoLabel)));
        $sector = Str::lower(Str::ascii(trim($sectorLabel)));

        if ($tipo === 'insumo') {
            return 'consumable';
        }

        if ($sector === 'textil') {
            return 'raw';
        }

        return 'consumable';
    }

    /**
     * @return array{quantity: float, unit: string}
     */
    public static function parseExistencia(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['quantity' => 0.0, 'unit' => 'pza'];
        }

        if (preg_match('/^([\d.,]+)\s*(.+)$/u', $raw, $matches) === 1) {
            $quantity = (float) str_replace(',', '.', $matches[1]);
            $unit = self::mapUnit(trim($matches[2]));

            return ['quantity' => $quantity, 'unit' => $unit];
        }

        $quantity = (float) str_replace(',', '.', preg_replace('/[^\d.,-]/', '', $raw) ?? '0');

        return ['quantity' => $quantity, 'unit' => 'pza'];
    }

    public static function mapUnit(string $unit): string
    {
        $normalized = Str::lower(trim($unit));
        if (in_array($normalized, SupplyUnits::values(), true)) {
            return $normalized;
        }

        return match ($normalized) {
            'cono' => 'pqt',
            default => 'pza',
        };
    }

    /**
     * @return array{is_active: bool, low_stock: bool}
     */
    public static function parseEstado(string $estado): array
    {
        $normalized = Str::lower(Str::ascii(trim($estado)));

        return match ($normalized) {
            'agotado', 'inactivo' => ['is_active' => false, 'low_stock' => false],
            'bajo' => ['is_active' => true, 'low_stock' => true],
            default => ['is_active' => true, 'low_stock' => false],
        };
    }
}
