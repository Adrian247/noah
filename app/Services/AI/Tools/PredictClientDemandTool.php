<?php

namespace App\Services\AI\Tools;

use App\Services\AI\Contracts\AiTool;
use App\Services\Predictive\ClientDemandPredictionService;

/**
 * Predicción de demanda de manufactura / suministro por cliente.
 */
class PredictClientDemandTool implements AiTool
{
    public function __construct(private readonly ClientDemandPredictionService $service) {}

    public function name(): string
    {
        return 'predict_client_demand';
    }

    public function description(): string
    {
        return 'Estima qué clientes requerirán pronto un servicio de manufactura o suministro '
            .'según el historial de rutinas aplicadas a esos clientes (no a activos). '
            .'La manufactura cubre trabajo productivo u obra para el cliente (no solo estructuras). '
            .'Indica línea (fabrication=manufactura, supply=suministro) o deja vacío para ambas. Solo lectura.';
    }

    public function requiredPermissions(): array
    {
        return ['assets.view', 'assets.manage', 'insights.use', 'routines.assign', 'clients.view'];
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'service_line' => [
                    'type' => 'string',
                    'description' => 'fabrication (manufactura), supply (suministro), o vacío para ambas.',
                ],
                'client_id' => [
                    'type' => 'integer',
                    'description' => 'Restringe a un cliente.',
                ],
                'horizon_days' => [
                    'type' => 'integer',
                    'description' => 'Ventana en días (7-90). Default 30.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Máximo de resultados (1-50). Default 10.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, int $companyId): array
    {
        $result = $this->service->predict($companyId, [
            'service_line' => $arguments['service_line'] ?? null,
            'client_id' => isset($arguments['client_id']) ? (int) $arguments['client_id'] : null,
            'horizon_days' => isset($arguments['horizon_days']) ? (int) $arguments['horizon_days'] : 30,
            'limit' => max(1, min(50, (int) ($arguments['limit'] ?? 10))),
        ]);

        $sources = [];
        foreach ($result['predictions'] as $prediction) {
            $sources[] = [
                'type' => 'client',
                'id' => (int) $prediction['client_id'],
                'label' => sprintf(
                    '%s · %s · score %.2f',
                    $prediction['client_name'],
                    $prediction['routine_type_name'],
                    $prediction['score'],
                ),
            ];
        }

        return ['data' => $result, 'sources' => $sources];
    }
}
