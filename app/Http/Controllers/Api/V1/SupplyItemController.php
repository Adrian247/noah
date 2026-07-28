<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SupplyItem;
use App\Models\SupplyType;
use App\Services\Forms\CatalogTypeFormCapture;
use App\Services\Forms\FormDesignSettings;
use App\Services\Forms\FormResponseValidator;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplyItemController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => SupplyItem::query()
                ->with('supplyType:id,code,name')
                ->orderBy('sku')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supply_type_id' => ['required', 'exists:supply_types,id'],
            'sku' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:32'],
            'standard_cost' => ['nullable', 'numeric', 'min:0'],
            'specifications' => ['nullable', 'array'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
        ]);

        $this->validateSpecificationsForType(
            (int) $data['supply_type_id'],
            $data['specifications'] ?? [],
        );

        $item = SupplyItem::query()->create($data);

        return response()->json(['data' => $item->load('supplyType')], 201);
    }

    public function update(Request $request, SupplyItem $supplyItem): JsonResponse
    {
        $data = $request->validate([
            'supply_type_id' => ['sometimes', 'exists:supply_types,id'],
            'sku' => ['sometimes', 'string', 'max:64'],
            'name' => ['sometimes', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:32'],
            'standard_cost' => ['nullable', 'numeric', 'min:0'],
            'specifications' => ['nullable', 'array'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
        ]);

        $typeId = (int) ($data['supply_type_id'] ?? $supplyItem->supply_type_id);
        $this->validateSpecificationsForType(
            $typeId,
            $data['specifications'] ?? $supplyItem->specifications ?? [],
        );

        $supplyItem->update($data);

        return response()->json(['data' => $supplyItem->fresh()->load('supplyType')]);
    }

    public function destroy(SupplyItem $supplyItem): JsonResponse
    {
        if ($supplyItem->consumptions()->exists()) {
            return response()->json(['message' => 'No se puede eliminar: el insumo está en consumos de rutinas.'], 422);
        }

        $supplyItem->delete();

        return response()->json(['message' => 'Deleted']);
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
