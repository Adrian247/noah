<?php

namespace App\Services\AI\Tools;

use App\Models\Site;
use App\Services\AI\Contracts\AiTool;

class ListSitesTool implements AiTool
{
    public function name(): string
    {
        return 'list_sites';
    }

    public function description(): string
    {
        return 'Lista sitios/ubicaciones de la empresa (solo lectura).';
    }

    public function requiredPermissions(): array
    {
        return ['sites.view', 'sites.manage'];
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Máximo de sitios (1-20). Default 10.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, int $companyId): array
    {
        $limit = max(1, min(20, (int) ($arguments['limit'] ?? 10)));
        $sites = Site::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->limit($limit)
            ->get();

        $sources = [];
        $data = $sites->map(function (Site $site) use (&$sources) {
            $sources[] = [
                'type' => 'site',
                'id' => $site->id,
                'label' => $site->name ?? 'Sitio #'.$site->id,
            ];

            return [
                'id' => $site->id,
                'name' => $site->name,
                'address' => $site->address,
            ];
        })->all();

        return ['data' => $data, 'sources' => $sources];
    }
}
