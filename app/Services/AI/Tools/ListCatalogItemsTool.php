<?php

namespace App\Services\AI\Tools;

use App\Models\CatalogItem;
use App\Services\AI\Contracts\AiTool;

class ListCatalogItemsTool implements AiTool
{
    public function name(): string
    {
        return 'list_catalog_items';
    }

    public function description(): string
    {
        return 'Lista artículos del catálogo de la empresa (solo lectura): código, nombre, fabricante y tipo.';
    }

    public function requiredPermissions(): array
    {
        return ['catalog.view', 'catalog.manage'];
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Filtro opcional por código, nombre o fabricante.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Máximo de resultados (1-30). Default 15.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, int $companyId): array
    {
        $limit = max(1, min(30, (int) ($arguments['limit'] ?? 15)));
        $query = trim((string) ($arguments['query'] ?? ''));

        $builder = CatalogItem::query()
            ->where('company_id', $companyId)
            ->where('is_system_template', false)
            ->with('equipmentType')
            ->orderBy('name');

        if ($query !== '') {
            $like = '%'.mb_strtolower($query).'%';
            $builder->where(function ($q) use ($like): void {
                $q->whereRaw('LOWER(code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(manufacturer, \'\')) LIKE ?', [$like]);
            });
        }

        $items = $builder->limit($limit)->get();
        $sources = [];
        $data = $items->map(function (CatalogItem $item) use (&$sources) {
            $sources[] = [
                'type' => 'catalog_item',
                'id' => $item->id,
                'label' => $item->code.' · '.$item->name,
            ];

            return [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'manufacturer' => $item->manufacturer,
                'equipment_type' => $item->equipmentType?->name,
            ];
        })->all();

        return ['data' => $data, 'sources' => $sources];
    }
}
