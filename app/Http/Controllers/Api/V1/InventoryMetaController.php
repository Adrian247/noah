<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\InventoryTaxonomy;
use App\Support\SupplyUnits;
use Illuminate\Http\JsonResponse;

class InventoryMetaController extends Controller
{
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => [
                'sectors' => InventoryTaxonomy::sectors(),
                'material_kinds' => InventoryTaxonomy::materialKinds(),
                'movement_types' => array_map(
                    fn (string $value) => [
                        'value' => $value,
                        'label' => InventoryTaxonomy::movementTypeLabel($value),
                    ],
                    InventoryTaxonomy::movementTypeValues(),
                ),
                'usage_types' => array_map(
                    fn (string $value) => [
                        'value' => $value,
                        'label' => InventoryTaxonomy::usageTypeLabel($value),
                    ],
                    InventoryTaxonomy::consumptionUsageTypeValues(),
                ),
                'units' => SupplyUnits::optionsForCurrentCompany(),
            ],
        ]);
    }
}
