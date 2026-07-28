<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EquipmentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EquipmentTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $items = EquipmentType::query()
            ->with('defaultFormDefinition:id,name,slug')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = app(\App\Support\CurrentCompany::class)->id();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', Rule::unique('equipment_types', 'code')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_form_definition_id' => ['nullable', 'exists:form_definitions,id'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $item = EquipmentType::query()->create($data);

        return response()->json(['data' => $item->fresh('defaultFormDefinition')], 201);
    }

    public function update(Request $request, EquipmentType $equipmentType): JsonResponse
    {
        $companyId = $equipmentType->company_id;

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:64', Rule::unique('equipment_types', 'code')->where('company_id', $companyId)->ignore($equipmentType->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_form_definition_id' => ['nullable', 'exists:form_definitions,id'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $equipmentType->update($data);

        return response()->json(['data' => $equipmentType->fresh('defaultFormDefinition')]);
    }

    public function destroy(EquipmentType $equipmentType): JsonResponse
    {
        if ($equipmentType->catalogItems()->exists()) {
            throw ValidationException::withMessages([
                'equipment_type' => ['No se puede eliminar: hay equipos de catálogo que usan este tipo.'],
            ]);
        }

        $equipmentType->delete();

        return response()->json(null, 204);
    }
}
