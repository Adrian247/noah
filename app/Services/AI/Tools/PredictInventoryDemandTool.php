<?php

namespace App\Services\AI\Tools;

use App\Enums\PredictiveAlgorithmKind;
use App\Services\AI\Contracts\AiTool;
use App\Services\Predictive\PredictiveAlgorithmVersionService;
use App\Services\Predictive\ServiceDemandEngine;

/**
 * Predicción de demanda de inventario: probabilidad de que un cliente solicite compra de artículos.
 */
class PredictInventoryDemandTool implements AiTool
{
    public function __construct(
        private readonly ServiceDemandEngine $engine,
        private readonly PredictiveAlgorithmVersionService $algorithms,
    ) {}

    public function name(): string
    {
        return 'predict_inventory_demand';
    }

    public function description(): string
    {
        return 'Estima qué clientes finales probablemente solicitarán compra de artículos del catálogo '
            .'(demanda de inventario) según consumos en servicios validados y calibración publicada. Solo lectura.';
    }

    public function requiredPermissions(): array
    {
        return ['assets.view', 'assets.manage', 'insights.use', 'routines.assign', 'clients.view', 'supplies.view'];
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
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
        $result = $this->engine->predictInventory($companyId, [
            'client_id' => isset($arguments['client_id']) ? (int) $arguments['client_id'] : null,
            'horizon_days' => isset($arguments['horizon_days']) ? (int) $arguments['horizon_days'] : 30,
            'limit' => max(1, min(50, (int) ($arguments['limit'] ?? 10))),
            'calibration' => $this->algorithms->publishedCalibration(PredictiveAlgorithmKind::Inventory),
        ]);

        $sources = [];
        foreach ($result['predictions'] as $prediction) {
            $sources[] = [
                'type' => 'client',
                'id' => (int) $prediction['client_id'],
                'label' => sprintf(
                    '%s · %s · score %.2f',
                    $prediction['client_name'],
                    $prediction['item_name'] ?? $prediction['catalog_item_code'] ?? 'artículo',
                    $prediction['score'],
                ),
            ];
        }

        return ['data' => $result, 'sources' => $sources];
    }
}
