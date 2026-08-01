<?php

namespace App\Services\AI\Tools;

use App\Models\Asset;
use App\Services\AI\Contracts\AiTool;

class SearchAssetsTool implements AiTool
{
    public function name(): string
    {
        return 'search_assets';
    }

    public function description(): string
    {
        return 'Busca activos por etiqueta (tag) o lista los más recientes si no hay query (solo lectura).';
    }

    public function requiredPermissions(): array
    {
        return ['assets.view', 'assets.manage'];
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Texto parcial de etiqueta (tag). Vacío = recientes.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Máximo de resultados (1-15). Default 5.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, int $companyId): array
    {
        $limit = max(1, min(15, (int) ($arguments['limit'] ?? 5)));
        $query = trim((string) ($arguments['query'] ?? ''));

        $builder = Asset::query()->where('company_id', $companyId);
        if ($query !== '') {
            $builder->whereRaw('LOWER(tag) LIKE ?', ['%'.mb_strtolower($query).'%']);
        }

        $assets = $builder->orderByDesc('updated_at')->limit($limit)->get();

        $sources = [];
        $data = $assets->map(function (Asset $asset) use (&$sources) {
            $sources[] = [
                'type' => 'asset',
                'id' => $asset->id,
                'label' => $asset->tag ?? 'Activo #'.$asset->id,
            ];

            return [
                'id' => $asset->id,
                'tag' => $asset->tag,
                'site_id' => $asset->site_id,
            ];
        })->all();

        return ['data' => $data, 'sources' => $sources];
    }
}
