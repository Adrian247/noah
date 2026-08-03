<?php

namespace App\Services\Analytics;

use App\Models\Asset;
use App\Models\Routine;
use App\Models\RoutineConsumption;
use App\Models\SupplyItem;
use Illuminate\Support\Collection;

class OperationalAnalyticsService
{
    /**
     * @return array{
     *     estimated_total: float,
     *     labor_estimate: float,
     *     supplies_estimate: float,
     *     sample_size: int,
     *     currency: string,
     * }
     */
    public function estimateRoutineCost(Routine $routine): array
    {
        $companyId = $routine->company_id;
        $typeId = $routine->routine_type_id;

        $historical = Routine::query()
            ->where('company_id', $companyId)
            ->where('routine_type_id', $typeId)
            ->where('is_demo', false)
            ->whereHas('invoice', fn ($q) => $q->where('status', 'issued'))
            ->with('invoice')
            ->limit(50)
            ->get();

        if ($historical->isEmpty()) {
            $historical = Routine::query()
                ->where('company_id', $companyId)
                ->where('routine_type_id', $typeId)
                ->whereHas('invoice')
                ->with('invoice')
                ->limit(20)
                ->get();
        }

        $totals = $historical
            ->map(fn (Routine $row) => (float) ($row->invoice?->total ?? 0))
            ->filter(fn (float $value) => $value > 0);

        $avg = $totals->isEmpty() ? 0.0 : $totals->avg();

        $labor = $avg > 0 ? round($avg * 0.55, 2) : 0.0;
        $supplies = $avg > 0 ? round($avg * 0.45, 2) : 0.0;

        return [
            'estimated_total' => round($avg, 2),
            'labor_estimate' => $labor,
            'supplies_estimate' => $supplies,
            'sample_size' => $totals->count(),
            'currency' => $routine->company?->currency ?? 'MXN',
        ];
    }

    /**
     * @return list<array{supply_item_id: int, sku: string|null, name: string, total_quantity: float, usage_count: int}>
     */
    public function suggestSuppliesForAsset(Asset $asset, int $limit = 8): array
    {
        $consumptions = RoutineConsumption::query()
            ->whereHas('execution.routine', function ($query) use ($asset): void {
                $query->where('asset_id', $asset->id);
            })
            ->with('supplyItem')
            ->get();

        if ($consumptions->isEmpty()) {
            return SupplyItem::query()
                ->where('company_id', $asset->company_id)
                ->where('is_active', true)
                ->orderByDesc('quantity_on_hand')
                ->limit($limit)
                ->get()
                ->map(fn (SupplyItem $item) => [
                    'supply_item_id' => $item->id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'total_quantity' => (float) $item->quantity_on_hand,
                    'usage_count' => 0,
                    'source' => 'catalog_fallback',
                ])
                ->all();
        }

        /** @var Collection<int, Collection<int, RoutineConsumption>> $grouped */
        $grouped = $consumptions->groupBy('supply_item_id');

        return $grouped
            ->map(function (Collection $rows) {
                $item = $rows->first()?->supplyItem;

                return [
                    'supply_item_id' => (int) $rows->first()?->supply_item_id,
                    'sku' => $item?->sku,
                    'name' => (string) ($item?->name ?? 'Insumo'),
                    'total_quantity' => round((float) $rows->sum('quantity'), 2),
                    'usage_count' => $rows->count(),
                    'source' => 'history',
                ];
            })
            ->sortByDesc('usage_count')
            ->take($limit)
            ->values()
            ->all();
    }
}
