<?php

namespace App\Services\AI\Tools;

use App\Services\AI\Contracts\AiTool;
use App\Services\Predictive\PredictiveMaintenanceService;

/**
 * Taxonomía de modos de falla, para que el asistente filtre predicciones con el mismo vocabulario.
 *
 * Sirve para traducir lo que pide el usuario ("se calienta", "pierde aceite") al código del catálogo
 * que después se usa como filtro en `predict_equipment_failures`.
 */
class ListFailureModesTool implements AiTool
{
    public function __construct(private readonly PredictiveMaintenanceService $service) {}

    public function name(): string
    {
        return 'list_failure_modes';
    }

    public function description(): string
    {
        return 'Lista el catálogo de modos de falla de la empresa con síntomas, causas típicas, '
            .'señales de monitoreo, códigos de alarma precursores y horas medias de reparación. '
            .'Úsalo para traducir una descripción libre al código que acepta predict_equipment_failures.';
    }

    public function requiredPermissions(): array
    {
        return ['assets.view', 'assets.manage', 'insights.use', 'catalog.view', 'routines.assign'];
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'equipment_class' => [
                    'type' => 'string',
                    'description' => 'Filtra los modos aplicables a una clase; acepta nombre o prefijo de tag: '
                        .'SCOOPTRAM/SS, CAMION_BAJO_PERFIL/VQ, JUMBO/JB, QUEBRADORA, MOLINO…',
                ],
                'system' => [
                    'type' => 'string',
                    'description' => 'Filtra por sistema: hidráulico, motor diésel, transmisión, eléctrico, frenos…',
                ],
            ],
        ];
    }

    public function execute(array $arguments, int $companyId): array
    {
        $modes = $this->service->failureModes(
            $companyId,
            $arguments['equipment_class'] ?? null,
            $arguments['system'] ?? null,
        );

        return [
            'data' => ['total' => count($modes), 'failure_modes' => $modes],
            'sources' => [[
                'type' => 'catalog',
                'id' => $companyId,
                'label' => 'Catálogo de modos de falla ('.count($modes).')',
            ]],
        ];
    }
}
