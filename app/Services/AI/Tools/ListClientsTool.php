<?php

namespace App\Services\AI\Tools;

use App\Models\Client;
use App\Services\AI\Contracts\AiTool;

class ListClientsTool implements AiTool
{
    public function name(): string
    {
        return 'list_clients';
    }

    public function description(): string
    {
        return 'Lista clientes de facturación de la empresa (solo lectura).';
    }

    public function requiredPermissions(): array
    {
        return ['clients.view', 'clients.manage'];
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Filtro opcional por nombre o código.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Máximo de clientes (1-20). Default 10.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, int $companyId): array
    {
        $limit = max(1, min(20, (int) ($arguments['limit'] ?? 10)));
        $query = trim((string) ($arguments['query'] ?? ''));

        $builder = Client::query()->where('company_id', $companyId)->orderBy('legal_name');
        if ($query !== '') {
            $builder->where(function ($q) use ($query) {
                $like = '%'.mb_strtolower($query).'%';
                $q->whereRaw('LOWER(legal_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(trade_name, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(code, \'\')) LIKE ?', [$like]);
            });
        }

        $clients = $builder->limit($limit)->get();
        $sources = [];
        $data = $clients->map(function (Client $client) use (&$sources) {
            $sources[] = [
                'type' => 'client',
                'id' => $client->id,
                'label' => $client->trade_name ?: ($client->legal_name ?? 'Cliente #'.$client->id),
            ];

            return [
                'id' => $client->id,
                'code' => $client->code,
                'legal_name' => $client->legal_name,
                'trade_name' => $client->trade_name,
                'is_active' => (bool) $client->is_active,
            ];
        })->all();

        return ['data' => $data, 'sources' => $sources];
    }
}
