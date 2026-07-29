<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SupplyItem;
use App\Services\Inventory\InventoryStockService;
use App\Support\InventoryTaxonomy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryMovementController extends Controller
{
    public function index(SupplyItem $supplyItem): JsonResponse
    {
        $rows = $supplyItem->movements()
            ->with(['routine:id', 'recorder:id,name'])
            ->orderByDesc('occurred_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request, SupplyItem $supplyItem, InventoryStockService $stock): JsonResponse
    {
        $data = $request->validate([
            'movement_type' => ['required', 'string', Rule::in(InventoryTaxonomy::movementTypeValues())],
            'quantity' => ['required', 'numeric'],
            'routine_id' => ['nullable', 'exists:routines,id'],
            'reference' => ['nullable', 'string', 'max:128'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $type = $data['movement_type'];
        $qty = (float) $data['quantity'];

        if ($type !== 'adjustment' && $qty <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['La cantidad debe ser mayor a cero.'],
            ]);
        }

        if ($type === 'adjustment' && $qty === 0.0) {
            throw ValidationException::withMessages([
                'quantity' => ['Indique el ajuste (+ entrada, − salida).'],
            ]);
        }

        $movement = $stock->recordMovement($supplyItem, [
            'movement_type' => $type,
            'quantity' => $qty,
            'routine_id' => $data['routine_id'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'recorded_by' => $request->user()?->id,
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);

        return response()->json([
            'data' => $movement->load(['routine:id', 'recorder:id,name']),
            'supply_item' => $supplyItem->fresh(),
        ], 201);
    }
}
