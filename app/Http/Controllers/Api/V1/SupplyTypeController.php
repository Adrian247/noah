<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SupplyType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupplyTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $items = SupplyType::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = app(\App\Support\CurrentCompany::class)->id();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', Rule::unique('supply_types', 'code')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $item = SupplyType::query()->create($data);

        return response()->json(['data' => $item], 201);
    }

    public function update(Request $request, SupplyType $supplyType): JsonResponse
    {
        $companyId = $supplyType->company_id;

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:64', Rule::unique('supply_types', 'code')->where('company_id', $companyId)->ignore($supplyType->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $supplyType->update($data);

        return response()->json(['data' => $supplyType->fresh()]);
    }

    public function destroy(SupplyType $supplyType): JsonResponse
    {
        if ($supplyType->supplyItems()->exists()) {
            throw ValidationException::withMessages([
                'supply_type' => ['No se puede eliminar: hay insumos que usan este tipo.'],
            ]);
        }

        $supplyType->delete();

        return response()->json(null, 204);
    }
}
