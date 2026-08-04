<?php

namespace App\Services\AI\Tools;

use App\Models\Client;
use App\Services\AI\Contracts\AiTool;

class GetClientDetailTool implements AiTool
{
    public function name(): string
    {
        return 'get_client_detail';
    }

    public function description(): string
    {
        return 'Detalle de un cliente (solo lectura) con submódulos: sitios e inventario de artículos vinculados.';
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
                'client_id' => [
                    'type' => 'integer',
                    'description' => 'ID numérico del cliente',
                ],
            ],
            'required' => ['client_id'],
        ];
    }

    public function execute(array $arguments, int $companyId): array
    {
        $id = (int) ($arguments['client_id'] ?? 0);
        $client = Client::query()
            ->where('company_id', $companyId)
            ->with([
                'sites' => fn ($q) => $q->orderBy('name')->limit(20),
                'inventoryAssets' => fn ($q) => $q->with('catalogItem')->orderByDesc('updated_at')->limit(20),
            ])
            ->find($id);

        if ($client === null) {
            return [
                'data' => ['error' => 'Cliente no encontrado en esta empresa.'],
                'sources' => [],
            ];
        }

        return [
            'data' => [
                'id' => $client->id,
                'code' => $client->code,
                'legal_name' => $client->legal_name,
                'trade_name' => $client->trade_name,
                'is_active' => (bool) $client->is_active,
                'sites' => $client->sites->map(fn ($site) => [
                    'id' => $site->id,
                    'name' => $site->name,
                    'address' => $site->address,
                ])->all(),
                'inventory' => $client->inventoryAssets->map(fn ($asset) => [
                    'id' => $asset->id,
                    'tag' => $asset->tag,
                    'serial_number' => $asset->serial_number,
                    'catalog_item' => $asset->catalogItem?->name,
                    'site_id' => $asset->site_id,
                ])->all(),
            ],
            'sources' => [[
                'type' => 'client',
                'id' => $client->id,
                'label' => $client->trade_name ?: ($client->legal_name ?? 'Cliente #'.$client->id),
            ]],
        ];
    }
}
