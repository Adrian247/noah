<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FormUsage;
use App\Http\Controllers\Controller;
use App\Models\FormDefinition;
use App\Models\SupplyType;
use App\Services\Forms\CatalogTypeFormCapture;
use App\Services\Forms\FormDesignSettings;
use App\Services\Forms\FormDefinitionGuard;
use App\Support\SupplyUnits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupplyTypeController extends Controller
{
    public function __construct(
        private readonly FormDefinitionGuard $formGuard,
    ) {}

    public function index(): JsonResponse
    {
        $items = SupplyType::query()
            ->with('defaultFormDefinition:id,name,slug')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function formOptions(): JsonResponse
    {
        $forms = FormDefinition::query()
            ->where('usage', FormUsage::Inventory)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return response()->json(['data' => $forms]);
    }

    public function unitOptions(): JsonResponse
    {
        return response()->json([
            'data' => SupplyUnits::optionsForCurrentCompany(),
        ]);
    }

    public function formCapture(
        SupplyType $supplyType,
        CatalogTypeFormCapture $capture,
        FormDesignSettings $designSettings,
    ): JsonResponse {
        return response()->json([
            'data' => $capture->forSupplyType($supplyType, $designSettings),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = app(\App\Support\CurrentCompany::class)->id();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', Rule::unique('supply_types', 'code')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_form_definition_id' => ['nullable', 'exists:form_definitions,id'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $this->formGuard->assertUsageForCompany(
            $data['default_form_definition_id'] ?? null,
            FormUsage::Inventory,
            $companyId,
        );

        $item = SupplyType::query()->create($data);

        return response()->json(['data' => $item->fresh('defaultFormDefinition')], 201);
    }

    public function update(Request $request, SupplyType $supplyType): JsonResponse
    {
        $companyId = $supplyType->company_id;

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:64', Rule::unique('supply_types', 'code')->where('company_id', $companyId)->ignore($supplyType->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_form_definition_id' => ['nullable', 'exists:form_definitions,id'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        if (array_key_exists('default_form_definition_id', $data)) {
            $this->formGuard->assertUsageForCompany(
                $data['default_form_definition_id'],
                FormUsage::Inventory,
                $companyId,
            );
        }

        $supplyType->update($data);

        return response()->json(['data' => $supplyType->fresh('defaultFormDefinition')]);
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
