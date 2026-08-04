<?php

namespace App\Services\Catalog;

use App\Models\Asset;
use App\Models\CatalogItem;
use Illuminate\Support\Str;

/**
 * Sincroniza inventario de cliente con artículos del catálogo tenant.
 */
final class ClientInventorySyncService
{
    public function syncFromCatalogItem(CatalogItem $catalogItem): void
    {
        Asset::query()
            ->where('sync_mode', 'linked')
            ->where(function ($q) use ($catalogItem) {
                $q->where('catalog_item_id', $catalogItem->id)
                    ->orWhere('base_catalog_item_id', $catalogItem->id);
            })
            ->each(function (Asset $asset) {
                if ($asset->sync_mode !== 'linked') {
                    return;
                }

                $baseId = $asset->base_catalog_item_id ?? $asset->catalog_item_id;
                $base = CatalogItem::query()->find($baseId);
                if ($base === null) {
                    return;
                }

                $asset->update([
                    'catalog_item_id' => $base->id,
                    'base_catalog_item_id' => $base->id,
                ]);
            });
    }

    public function detachToCustomCopy(Asset $asset): CatalogItem
    {
        $base = $asset->catalogItem;
        if ($base === null) {
            throw new \InvalidArgumentException('El artículo de inventario no tiene catálogo vinculado.');
        }

        if ($asset->sync_mode === 'detached' && $base->is_detached_copy) {
            return $base;
        }

        $clone = CatalogItem::query()->create([
            'company_id' => $base->company_id,
            'equipment_type_id' => $base->equipment_type_id,
            'code' => $this->uniqueCode($base->company_id, $base->code.'-cli-'.$asset->id),
            'name' => $base->name,
            'manufacturer' => $base->manufacturer,
            'oem_equipment_model_id' => $base->oem_equipment_model_id,
            'specifications' => $base->specifications,
            'image_path' => $base->image_path,
            'is_detached_copy' => true,
        ]);

        $asset->update([
            'catalog_item_id' => $clone->id,
            'base_catalog_item_id' => $base->id,
            'sync_mode' => 'detached',
            'detached_at' => now(),
        ]);

        return $clone;
    }

    public function resetToCatalogBase(Asset $asset): void
    {
        $baseId = $asset->base_catalog_item_id ?? $asset->catalog_item_id;
        $base = CatalogItem::query()->find($baseId);
        if ($base === null) {
            throw new \InvalidArgumentException('No hay catálogo base para restablecer.');
        }

        if ($asset->sync_mode === 'detached' && $asset->catalogItem?->is_detached_copy) {
            $asset->catalogItem->delete();
        }

        $asset->update([
            'catalog_item_id' => $base->id,
            'base_catalog_item_id' => $base->id,
            'sync_mode' => 'linked',
            'detached_at' => null,
        ]);
    }

    private function uniqueCode(int $companyId, string $code): string
    {
        $base = Str::limit($code, 58, '');
        $candidate = $base;
        $i = 1;
        while (CatalogItem::query()->where('company_id', $companyId)->where('code', $candidate)->exists()) {
            $candidate = $base.'-'.$i;
            $i++;
        }

        return $candidate;
    }
}
