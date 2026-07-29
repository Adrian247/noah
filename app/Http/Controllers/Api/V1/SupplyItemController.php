<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SupplyItem;
use App\Models\SupplyType;
use App\Services\Forms\CatalogTypeFormCapture;
use App\Services\Forms\FormDesignSettings;
use App\Services\Forms\FormResponseValidator;
use App\Services\Inventory\InventoryStockService;
use App\Support\CurrentCompany;
use App\Support\InventoryTaxonomy;
use App\Support\SupplyUnits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplyItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SupplyItem::query()
            ->with('supplyType:id,code,name')
            ->orderBy('sku');

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->trim().'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term)
                    ->orWhere('storage_location', 'like', $term);
            });
        }

        if ($request->filled('sector')) {
            $query->where('sector', $request->string('sector'));
        }

        if ($request->filled('supply_type_id')) {
            $query->where('supply_type_id', $request->integer('supply_type_id'));
        }

        if ($request->boolean('low_stock')) {
            $query->whereNotNull('min_stock')
                ->whereColumn('quantity_on_hand', '<=', 'min_stock');
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request, InventoryStockService $stock): JsonResponse
    {
        $data = $this->validatedItem($request);

        $opening = (float) ($data['opening_quantity'] ?? 0);
        unset($data['opening_quantity']);

        $data['quantity_on_hand'] = 0;

        $item = SupplyItem::query()->create($data);

        if ($opening > 0) {
            $stock->recordMovement($item, [
                'movement_type' => 'in',
                'quantity' => $opening,
                'reference' => 'stock-inicial',
                'notes' => 'Existencia al crear artículo',
                'recorded_by' => $request->user()?->id,
            ]);
            $item->refresh();
        }

        return response()->json(['data' => $item->load('supplyType')], 201);
    }

    public function update(Request $request, SupplyItem $supplyItem): JsonResponse
    {
        $data = $this->validatedItem($request, partial: true);
        unset($data['opening_quantity']);

        $supplyItem->update($data);

        return response()->json(['data' => $supplyItem->fresh()->load('supplyType')]);
    }

    public function destroy(SupplyItem $supplyItem): JsonResponse
    {
        if ($supplyItem->consumptions()->exists()) {
            return response()->json(['message' => 'No se puede eliminar: el artículo está en consumos de rutinas.'], 422);
        }

        if ($supplyItem->movements()->exists()) {
            return response()->json(['message' => 'No se puede eliminar: el artículo tiene movimientos registrados.'], 422);
        }

        $supplyItem->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function import(Request $request, InventoryStockService $stock): JsonResponse
    {
        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:500'],
            'rows.*.sku' => ['required', 'string', 'max:64'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.supply_type_code' => ['nullable', 'string', 'max:64'],
            'rows.*.sector' => ['nullable', 'string', Rule::in(InventoryTaxonomy::sectorValues())],
            'rows.*.material_kind' => ['nullable', 'string', Rule::in(InventoryTaxonomy::materialKindValues())],
            'rows.*.unit' => ['nullable', 'string', Rule::in(SupplyUnits::values())],
            'rows.*.quantity_on_hand' => ['nullable', 'numeric', 'min:0'],
            'rows.*.min_stock' => ['nullable', 'numeric', 'min:0'],
            'rows.*.storage_location' => ['nullable', 'string', 'max:255'],
            'rows.*.is_active' => ['nullable', 'boolean'],
        ]);

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($validated['rows'] as $index => $row) {
            $type = null;
            if (! empty($row['supply_type_code'])) {
                $type = SupplyType::query()->where('code', $row['supply_type_code'])->first();
                if ($type === null) {
                    $errors[] = ['row' => $index, 'message' => 'Tipo de insumo no encontrado: '.$row['supply_type_code']];

                    continue;
                }
            } else {
                $type = SupplyType::query()->orderBy('id')->first();
                if ($type === null) {
                    $errors[] = ['row' => $index, 'message' => 'No hay tipos de insumo en la empresa.'];

                    continue;
                }
            }

            $item = SupplyItem::query()->where('sku', $row['sku'])->first();
            $payload = [
                'supply_type_id' => $type->id,
                'sku' => $row['sku'],
                'name' => $row['name'],
                'sector' => $row['sector'] ?? 'mechanical',
                'material_kind' => $row['material_kind'] ?? 'consumable',
                'unit' => $row['unit'] ?? 'pza',
                'min_stock' => $row['min_stock'] ?? null,
                'storage_location' => $row['storage_location'] ?? null,
                'is_active' => array_key_exists('is_active', $row) ? (bool) $row['is_active'] : true,
            ];

            try {
                if ($item === null) {
                    $payload['quantity_on_hand'] = 0;
                    $item = SupplyItem::query()->create($payload);
                    $created++;
                } else {
                    $item->update($payload);
                    $updated++;
                }

                if (array_key_exists('quantity_on_hand', $row) && $row['quantity_on_hand'] !== null) {
                    $target = (float) $row['quantity_on_hand'];
                    $current = (float) $item->fresh()->quantity_on_hand;
                    if ($target !== $current) {
                        if ($target > $current) {
                            $stock->recordMovement($item, [
                                'movement_type' => 'in',
                                'quantity' => $target - $current,
                                'reference' => 'import-excel',
                                'notes' => 'Importación Excel',
                                'recorded_by' => $request->user()?->id,
                            ]);
                        } else {
                            $stock->recordMovement($item, [
                                'movement_type' => 'out',
                                'quantity' => $current - $target,
                                'reference' => 'import-excel',
                                'notes' => 'Importación Excel',
                                'recorded_by' => $request->user()?->id,
                            ]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = ['row' => $index, 'message' => $e->getMessage()];
            }
        }

        return response()->json([
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'errors' => $errors,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedItem(Request $request, bool $partial = false): array
    {
        $rules = [
            'supply_type_id' => [$partial ? 'sometimes' : 'required', 'exists:supply_types,id'],
            'sku' => [$partial ? 'sometimes' : 'required', 'string', 'max:64'],
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'sector' => ['sometimes', 'string', Rule::in(InventoryTaxonomy::sectorValues())],
            'material_kind' => ['sometimes', 'string', Rule::in(InventoryTaxonomy::materialKindValues())],
            'unit' => ['nullable', 'string', Rule::in(SupplyUnits::values())],
            'standard_cost' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'storage_location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'specifications' => ['nullable', 'array'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'opening_quantity' => ['nullable', 'numeric', 'min:0'],
        ];

        $data = $request->validate($rules);

        if (array_key_exists('supply_type_id', $data) || array_key_exists('specifications', $data)) {
            $typeId = (int) ($data['supply_type_id'] ?? $request->route('supplyItem')?->supply_type_id);
            if ($typeId) {
                $this->validateSpecificationsForType(
                    $typeId,
                    $data['specifications'] ?? $request->route('supplyItem')?->specifications ?? [],
                );
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $specifications
     */
    private function validateSpecificationsForType(int $supplyTypeId, array $specifications): void
    {
        $type = SupplyType::query()->findOrFail($supplyTypeId);
        $payload = app(CatalogTypeFormCapture::class)->forSupplyType($type, app(FormDesignSettings::class));

        if (! ($payload['configured'] ?? false)) {
            return;
        }

        $schema = $payload['schema'] ?? null;
        if (! is_array($schema)) {
            return;
        }

        app(FormResponseValidator::class)->validate(
            $schema,
            $specifications,
            app(CurrentCompany::class)->id(),
        );
    }
}
