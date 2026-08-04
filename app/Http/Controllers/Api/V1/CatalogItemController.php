<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CatalogItem;
use App\Models\EquipmentType;
use App\Services\Forms\CatalogTypeFormCapture;
use App\Services\Forms\FormDesignSettings;
use App\Services\Forms\FormResponseValidator;
use App\Services\Catalog\ClientInventorySyncService;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogItemController extends Controller
{
    public function __construct(
        private readonly ClientInventorySyncService $inventorySync,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => CatalogItem::query()
                ->where('is_system_template', false)
                ->with([
                    'equipmentType:id,code,name',
                    'oemEquipmentModel:id,manufacturer,model,equipment_class,family',
                ])
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
            'oem_equipment_model_id' => ['nullable', 'integer', 'exists:oem_equipment_models,id'],
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
            'oem_equipment_model_id' => ['nullable', 'integer', 'exists:oem_equipment_models,id'],
            'specifications' => ['nullable', 'array'],
        ]);

        $typeId = (int) ($data['equipment_type_id'] ?? $catalogItem->equipment_type_id);
        $this->validateSpecificationsForType(
            $typeId,
            $data['specifications'] ?? $catalogItem->specifications ?? [],
        );

        $catalogItem->update($data);

        if (! $catalogItem->is_detached_copy) {
            $this->inventorySync->syncFromCatalogItem($catalogItem);
        }

        return response()->json(['data' => $catalogItem->fresh()->load('equipmentType')]);
    }

    public function updateImage(Request $request, CatalogItem $catalogItem): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:4096', 'mimes:jpg,jpeg,png,webp'],
        ]);

        if ($catalogItem->image_path && Storage::disk('public')->exists($catalogItem->image_path)) {
            Storage::disk('public')->delete($catalogItem->image_path);
        }

        $path = $request->file('image')->store('catalog-items', 'public');
        $catalogItem->update(['image_path' => $path]);

        if (! $catalogItem->is_detached_copy) {
            $this->inventorySync->syncFromCatalogItem($catalogItem);
        }

        return response()->json(['data' => $catalogItem->fresh()->load('equipmentType')]);
    }

    public function destroy(CatalogItem $catalogItem): JsonResponse
    {
        if ($catalogItem->assets()->exists()) {
            return response()->json(['message' => 'No se puede eliminar: hay artículos de cliente que usan este artículo de catálogo.'], 422);
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
