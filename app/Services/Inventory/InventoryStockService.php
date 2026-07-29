<?php

namespace App\Services\Inventory;

use App\Models\InventoryMovement;
use App\Models\SupplyItem;
use App\Support\InventoryTaxonomy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryStockService
{
    /**
     * @param  array{
     *     movement_type: string,
     *     quantity: float,
     *     routine_id?: int|null,
     *     routine_execution_id?: int|null,
     *     reference?: string|null,
     *     notes?: string|null,
     *     recorded_by?: int|null,
     *     occurred_at?: \DateTimeInterface|string|null,
     * }  $data
     */
    public function recordMovement(SupplyItem $supplyItem, array $data): InventoryMovement
    {
        $type = $data['movement_type'];
        if (! in_array($type, InventoryTaxonomy::movementTypeValues(), true)) {
            throw ValidationException::withMessages(['movement_type' => ['Tipo de movimiento no válido.']]);
        }

        $qty = (float) $data['quantity'];
        $delta = $this->deltaForType($type, $qty);

        return DB::transaction(function () use ($supplyItem, $data, $type, $qty, $delta) {
            $locked = SupplyItem::query()->whereKey($supplyItem->id)->lockForUpdate()->firstOrFail();
            $newBalance = (float) $locked->quantity_on_hand + $delta;

            if ($newBalance < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Stock insuficiente para este movimiento.'],
                ]);
            }

            $locked->update(['quantity_on_hand' => $newBalance]);

            return InventoryMovement::query()->create([
                'company_id' => $locked->company_id,
                'supply_item_id' => $locked->id,
                'routine_id' => $data['routine_id'] ?? null,
                'routine_execution_id' => $data['routine_execution_id'] ?? null,
                'movement_type' => $type,
                'quantity' => $type === 'adjustment' ? $qty : abs($qty),
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $data['recorded_by'] ?? null,
                'occurred_at' => $data['occurred_at'] ?? now(),
            ]);
        });
    }

    public function deltaForType(string $type, float $quantity): float
    {
        return match ($type) {
            'in', 'consignment_return' => abs($quantity),
            'out', 'consignment', 'write_off' => -abs($quantity),
            'adjustment' => $quantity,
            default => 0,
        };
    }
}
