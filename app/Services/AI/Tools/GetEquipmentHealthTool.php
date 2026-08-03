<?php

namespace App\Services\AI\Tools;

use App\Models\Asset;
use App\Services\AI\Contracts\AiTool;
use App\Services\Predictive\PredictiveMaintenanceService;

/**
 * Ficha de salud de un equipo: la evidencia detrás de la predicción.
 *
 * Complementa a `predict_equipment_failures`: cuando el asistente ya sabe qué equipo preocupa,
 * esta tool trae confiabilidad, últimas alarmas, fallas, órdenes pendientes y vida de componentes
 * para que la recomendación se sostenga con datos citables.
 */
class GetEquipmentHealthTool implements AiTool
{
    public function __construct(private readonly PredictiveMaintenanceService $service) {}

    public function name(): string
    {
        return 'get_equipment_health';
    }

    public function description(): string
    {
        return 'Ficha de salud de un equipo por tag o id. La predicción se basa en el historial de '
            .'rutinas aplicadas al activo (frecuencia, atrasos, consumos, validaciones), más señales '
            .'de referencia si existen. Incluye MTBF/MTTR, alarmas, fallas recientes y predicción vigente. '
            .'Requiere tag o asset_id. Solo lectura.';
    }

    public function requiredPermissions(): array
    {
        return ['assets.view', 'assets.manage', 'insights.use', 'routines.assign'];
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'tag' => [
                    'type' => 'string',
                    'description' => 'Etiqueta del equipo, p. ej. "SS-305". Alternativa a asset_id.',
                ],
                'asset_id' => [
                    'type' => 'integer',
                    'description' => 'Id del activo. Alternativa a tag.',
                ],
                'as_of' => [
                    'type' => 'string',
                    'description' => 'Fecha de corte YYYY-MM-DD. Default hoy.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, int $companyId): array
    {
        $asset = $this->resolveAsset($arguments, $companyId);

        if ($asset === null) {
            return [
                'data' => ['error' => 'No se encontró el equipo indicado en esta empresa.'],
                'sources' => [],
            ];
        }

        $health = $this->service->health($companyId, $asset, $arguments['as_of'] ?? null);

        return [
            'data' => $health,
            'sources' => [[
                'type' => 'asset',
                'id' => (int) $asset->id,
                'label' => $asset->tag ?? 'Activo #'.$asset->id,
            ]],
        ];
    }

    private function resolveAsset(array $arguments, int $companyId): ?Asset
    {
        $query = Asset::query()->where('company_id', $companyId);

        if (! empty($arguments['asset_id'])) {
            return $query->find((int) $arguments['asset_id']);
        }

        $tag = trim((string) ($arguments['tag'] ?? ''));
        if ($tag === '') {
            return null;
        }

        // Coincidencia exacta primero: "SS-30" no debe devolver SS-305 si SS-30 existe.
        return $query->where('tag', 'ilike', $tag)->first()
            ?? $query->where('tag', 'ilike', '%'.$tag.'%')->orderBy('tag')->first();
    }
}
