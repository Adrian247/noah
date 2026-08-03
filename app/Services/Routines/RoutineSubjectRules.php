<?php

namespace App\Services\Routines;

use App\Enums\ServiceLine;
use App\Models\RoutineType;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de sujeto de una rutina según la línea de servicio del tipo.
 */
final class RoutineSubjectRules
{
    /**
     * @param  array{asset_id?: mixed, client_id?: mixed}  $data
     * @return array{asset_id: int|null, client_id: int|null}
     */
    public function normalizeForType(RoutineType $type, array $data): array
    {
        $line = $type->service_line instanceof ServiceLine
            ? $type->service_line
            : ServiceLine::tryFrom((string) $type->service_line) ?? ServiceLine::Maintenance;

        $assetId = isset($data['asset_id']) && $data['asset_id'] !== '' && $data['asset_id'] !== null
            ? (int) $data['asset_id']
            : null;
        $clientId = isset($data['client_id']) && $data['client_id'] !== '' && $data['client_id'] !== null
            ? (int) $data['client_id']
            : null;

        if ($line->requiresAsset() && $assetId === null) {
            throw ValidationException::withMessages([
                'asset_id' => ['Las rutinas de mantenimiento requieren un activo.'],
            ]);
        }

        if ($line->requiresClient() && $clientId === null) {
            throw ValidationException::withMessages([
                'client_id' => ['Las rutinas de '.$line->label().' requieren un cliente.'],
            ]);
        }

        if ($line === ServiceLine::Maintenance) {
            // Cliente opcional en mantenimiento (puede resolverse por assignment).
            return ['asset_id' => $assetId, 'client_id' => $clientId];
        }

        // Manufactura / suministro: activo opcional.
        return ['asset_id' => $assetId, 'client_id' => $clientId];
    }
}
