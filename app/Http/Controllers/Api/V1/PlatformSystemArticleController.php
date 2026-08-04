<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CatalogItem;
use App\Models\EquipmentType;
use App\Support\PlatformCatalogCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PlatformSystemArticleController extends Controller
{
    public function index(): JsonResponse
    {
        $companyId = app(PlatformCatalogCompany::class)->id();

        $items = CatalogItem::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('is_system_template', true)
            ->with(['equipmentType:id,code,name'])
            ->orderBy('code')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function types(): JsonResponse
    {
        $companyId = app(PlatformCatalogCompany::class)->id();

        $types = EquipmentType::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return response()->json(['data' => $types]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = app(PlatformCatalogCompany::class)->id();

        $data = $request->validate([
            'equipment_type_id' => ['nullable', 'integer'],
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'oem_equipment_model_id' => ['nullable', 'integer', 'exists:oem_equipment_models,id'],
            'specifications' => ['nullable', 'array'],
        ]);

        $typeId = isset($data['equipment_type_id'])
            ? (int) $data['equipment_type_id']
            : $this->ensureDefaultType($companyId)->id;

        $type = EquipmentType::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->whereKey($typeId)
            ->first();
        if ($type === null) {
            $type = $this->ensureDefaultType($companyId);
        }

        $item = CatalogItem::withoutGlobalScope('company')->create([
            'company_id' => $companyId,
            'is_system_template' => true,
            'equipment_type_id' => $type->id,
            'code' => $data['code'],
            'name' => $data['name'],
            'manufacturer' => $data['manufacturer'] ?? null,
            'oem_equipment_model_id' => $data['oem_equipment_model_id'] ?? null,
            'specifications' => $data['specifications'] ?? null,
        ]);

        return response()->json(['data' => $item->load('equipmentType')], 201);
    }

    public function update(Request $request, CatalogItem $catalogItem): JsonResponse
    {
        $this->assertSystemTemplate($catalogItem);

        $data = $request->validate([
            'equipment_type_id' => ['sometimes', 'integer'],
            'code' => ['sometimes', 'string', 'max:64'],
            'name' => ['sometimes', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'oem_equipment_model_id' => ['nullable', 'integer', 'exists:oem_equipment_models,id'],
            'specifications' => ['nullable', 'array'],
        ]);

        $catalogItem->update($data);

        return response()->json(['data' => $catalogItem->fresh()->load('equipmentType')]);
    }

    public function updateImage(Request $request, CatalogItem $catalogItem): JsonResponse
    {
        $this->assertSystemTemplate($catalogItem);

        $request->validate([
            'image' => ['required', 'image', 'max:4096', 'mimes:jpg,jpeg,png,webp'],
        ]);

        if ($catalogItem->image_path && Storage::disk('public')->exists($catalogItem->image_path)) {
            Storage::disk('public')->delete($catalogItem->image_path);
        }

        $path = $request->file('image')->store('system-articles', 'public');
        $catalogItem->update(['image_path' => $path]);

        return response()->json(['data' => $catalogItem->fresh()->load('equipmentType')]);
    }

    public function destroy(CatalogItem $catalogItem): JsonResponse
    {
        $this->assertSystemTemplate($catalogItem);

        if ($catalogItem->assets()->exists()) {
            return response()->json(['message' => 'No se puede eliminar: hay referencias activas.'], 422);
        }

        $catalogItem->delete();

        return response()->json(null, 204);
    }

    private function ensureDefaultType(int $companyId): EquipmentType
    {
        return EquipmentType::withoutGlobalScope('company')->firstOrCreate(
            ['company_id' => $companyId, 'code' => 'SYS-GEN'],
            [
                'name' => 'General',
                'description' => 'Tipo por defecto de artículos de sistema',
                'sort_order' => 0,
            ],
        );
    }

    private function assertSystemTemplate(CatalogItem $catalogItem): void
    {
        if (! $catalogItem->is_system_template) {
            abort(404);
        }
    }
}
