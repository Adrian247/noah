<?php

namespace App\Services\AI\Tools;

use App\Models\SupplyItem;
use App\Services\AI\Contracts\AiTool;

class ListSupplyItemsTool implements AiTool
{
    public function name(): string
    {
        return 'list_supply_items';
    }

    public function description(): string
    {
        return 'Lista insumos/refacciones del catálogo de la empresa (solo lectura).';
    }

    public function requiredPermissions(): array
    {
        return ['inventory.view', 'inventory.manage'];
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Filtro opcional por nombre.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Máximo de ítems (1-20). Default 10.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, int $companyId): array
    {
        $limit = max(1, min(20, (int) ($arguments['limit'] ?? 10)));
        $query = trim((string) ($arguments['query'] ?? ''));

        $builder = SupplyItem::query()->where('company_id', $companyId);
        if ($query !== '') {
            $builder->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($query).'%']);
        }

        $items = $builder->orderBy('name')->limit($limit)->get();

        $sources = [];
        $data = $items->map(function (SupplyItem $item) use (&$sources) {
            $sources[] = [
                'type' => 'supply_item',
                'id' => $item->id,
                'label' => $item->name ?? 'Insumo #'.$item->id,
            ];

            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku ?? null,
                'unit' => $item->unit ?? null,
            ];
        })->all();

        return ['data' => $data, 'sources' => $sources];
    }
}
