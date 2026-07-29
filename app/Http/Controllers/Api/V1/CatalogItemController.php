<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CatalogItem;
use App\Models\EquipmentType;
use App\Services\Forms\CatalogTypeFormCapture;
use App\Services\Forms\FormDesignSettings;
use App\Services\Forms\FormResponseValidator;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogItemController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => CatalogItem::query()
                ->with('equipmentType:id,code,name')
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'equipment_type_id' => ['required', 'exists:equipment_types,id'],
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'specifications' => ['nullable', 'array'],
        ]);

        $this->validateSpecificationsForType(
            (int) $data['equipment_type_id'],
            $data['specifications'] ?? [],
        );

        $item = CatalogItem::query()->create($data);

        return response()->json(['data' => $item->load('equipmentType')], 201);
    }

    public function update(Request $request, CatalogItem $catalogItem): JsonResponse
    {
        $data = $request->validate([
            'equipment_type_id' => ['sometimes', 'exists:equipment_types,id'],
            'code' => ['sometimes', 'string', 'max:64'],
            'name' => ['sometimes', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'specifications' => ['nullable', 'array'],
        ]);

        $typeId = (int) ($data['equipment_type_id'] ?? $catalogItem->equipment_type_id);
        $this->validateSpecificationsForType(
            $typeId,
            $data['specifications'] ?? $catalogItem->specifications ?? [],
        );

        $catalogItem->update($data);

        return response()->json(['data' => $catalogItem->fresh()->load('equipmentType')]);
    }

    public function destroy(CatalogItem $catalogItem): JsonResponse
    {
        if ($catalogItem->assets()->exists()) {
            return response()->json(['message' => 'No se puede eliminar: hay activos que usan este equipo de catálogo.'], 422);
        }

        $catalogItem->delete();

        return response()->json(['message' => 'Deleted']);
    }

    /**
     * @param  array<string, mixed>  $specifications
     */
    private function validateSpecificationsForType(int $equipmentTypeId, array $specifications): void
    {
        $type = EquipmentType::query()->findOrFail($equipmentTypeId);
        $capture = app(CatalogTypeFormCapture::class);
        $design = app(FormDesignSettings::class);
        $payload = $capture->forEquipmentType($type, $design);

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
