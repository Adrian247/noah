<?php

namespace App\Listeners;

use App\Enums\ServiceCategory;
use App\Events\RoutineValidated;
use App\Models\SupplyItem;
use App\Services\Inventory\InventoryStockService;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\Log;

/**
 * En fabricación: si un consumo no tiene movimiento de inventario (edge cases),
 * aplica baja al validar. El costo queda en el borrador de factura vía CreateInvoiceDraft.
 */
class EnsureManufacturingInventoryWriteOff
{
    public function __construct(
        private readonly InventoryStockService $stock,
    ) {}

    public function handle(RoutineValidated $event): void
    {
        $routine = $event->routine->load(['routineType', 'company']);
        $category = $routine->routineType?->service_category;

        if (! $category instanceof ServiceCategory || ! $category->deductsInventoryOnManufacturing()) {
            return;
        }

        app()->instance(CurrentCompany::class, new CurrentCompany($routine->company));

        $execution = $event->execution->load(['consumptions.supplyItem']);

        foreach ($execution->consumptions as $consumption) {
            if ($consumption->inventory_movement_id !== null) {
                continue;
            }

            $supply = $consumption->supplyItem;
            if (! $supply instanceof SupplyItem) {
                continue;
            }

            try {
                $movement = $this->stock->recordMovement($supply, [
                    'movement_type' => 'write_off',
                    'quantity' => (float) $consumption->quantity,
                    'routine_id' => $routine->id,
                    'routine_execution_id' => $execution->id,
                    'notes' => 'Baja automática por servicio de fabricación #'.$routine->id,
                    'reference' => 'routine:'.$routine->id,
                ]);
                $consumption->update(['inventory_movement_id' => $movement->id]);
            } catch (\Throwable $e) {
                Log::warning('manufacturing.write_off_failed', [
                    'routine_id' => $routine->id,
                    'consumption_id' => $consumption->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
