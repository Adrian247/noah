<?php

namespace App\Services\Routines;

use App\Enums\ServiceCategory;
use App\Models\Asset;
use App\Models\RoutineType;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de sujeto de un servicio según la categoría del tipo.
 */
final class RoutineSubjectRules
{
    /**
     * @param  array{asset_id?: mixed, client_id?: mixed}  $data
     * @return array{asset_id: int|null, client_id: int|null}
     */
    public function normalizeForType(RoutineType $type, array $data): array
    {
        $category = $type->service_category instanceof ServiceCategory
            ? $type->service_category
            : ServiceCategory::tryFrom((string) $type->service_category) ?? ServiceCategory::Maintenance;

        $assetId = isset($data['asset_id']) && $data['asset_id'] !== '' && $data['asset_id'] !== null
            ? (int) $data['asset_id']
            : null;
        $clientId = isset($data['client_id']) && $data['client_id'] !== '' && $data['client_id'] !== null
            ? (int) $data['client_id']
            : null;

        if ($assetId !== null) {
            $asset = Asset::query()->find($assetId);
            if ($asset === null) {
                throw ValidationException::withMessages([
                    'asset_id' => ['El artículo de inventario no existe.'],
                ]);
            }
            if ($asset->client_id === null) {
                throw ValidationException::withMessages([
                    'asset_id' => ['El artículo no pertenece al inventario de un cliente.'],
                ]);
            }
            if ($clientId !== null && $clientId !== (int) $asset->client_id) {
                throw ValidationException::withMessages([
                    'client_id' => ['El cliente no coincide con el inventario del artículo seleccionado.'],
                ]);
            }
            $clientId = (int) $asset->client_id;
        }

        if ($category->requiresClientArticle() && $assetId === null) {
            throw ValidationException::withMessages([
                'asset_id' => ['Los servicios de mantenimiento requieren un artículo del inventario del cliente.'],
            ]);
        }

        if ($category->requiresClient() && $clientId === null) {
            throw ValidationException::withMessages([
                'client_id' => ['Los servicios de '.$category->label().' requieren un cliente.'],
            ]);
        }

        return ['asset_id' => $assetId, 'client_id' => $clientId];
    }
}
