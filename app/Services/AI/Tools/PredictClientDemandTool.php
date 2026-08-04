<?php

namespace App\Services\AI\Tools;

use App\Enums\PredictiveAlgorithmKind;
use App\Services\AI\Contracts\AiTool;
use App\Services\Predictive\ClientDemandPredictionService;
use App\Services\Predictive\PredictiveAlgorithmVersionService;
use App\Services\Predictive\ServiceDemandEngine;

/**
 * Predicción de demanda de manufactura / instalación por cliente.
 */
class PredictClientDemandTool implements AiTool
{
    public function __construct(
        private readonly ClientDemandPredictionService $legacy,
        private readonly ServiceDemandEngine $demandEngine,
        private readonly PredictiveAlgorithmVersionService $algorithms,
    ) {}

    public function name(): string
    {
        return 'predict_client_demand';
    }

    public function description(): string
    {
        return 'Estima qué clientes requerirán pronto un servicio de manufactura o instalación '
            .'según el historial de servicios aplicados a esos clientes (no a activos). '
            .'Indica línea (manufacturing/fabrication=manufactura, installation=instalación) '
            .'o deja vacío para ambas. Solo lectura.';
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
                    'description' => 'manufacturing/fabrication, installation, o vacío para ambas.',
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
        $line = $arguments['service_line'] ?? null;
        $filters = [
            'service_line' => $line,
            'client_id' => isset($arguments['client_id']) ? (int) $arguments['client_id'] : null,
            'horizon_days' => isset($arguments['horizon_days']) ? (int) $arguments['horizon_days'] : 30,
            'limit' => max(1, min(50, (int) ($arguments['limit'] ?? 10))),
            'calibration' => $this->algorithms->publishedCalibration(PredictiveAlgorithmKind::Manufacturing),
        ];

        if (in_array($line, ['manufacturing', 'fabrication'], true)) {
            $result = $this->demandEngine->predictManufacturing($companyId, $filters);
        } elseif ($line === 'installation') {
            $result = $this->legacy->predict($companyId, $filters);
        } else {
            $manufacturing = $this->demandEngine->predictManufacturing($companyId, $filters);
            $installation = $this->legacy->predict($companyId, array_merge($filters, [
                'service_category' => 'installation',
            ]));
            $merged = array_merge($manufacturing['predictions'] ?? [], $installation['predictions'] ?? []);
            usort($merged, fn (array $a, array $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
            $limit = (int) $filters['limit'];
            $result = [
                'as_of' => $manufacturing['as_of'] ?? $installation['as_of'] ?? null,
                'horizon_days' => $filters['horizon_days'],
                'predictions' => array_slice($merged, 0, $limit),
                'notes' => array_values(array_filter(array_merge(
                    $manufacturing['notes'] ?? [],
                    $installation['notes'] ?? [],
                ))),
            ];
        }

        $sources = [];
        foreach ($result['predictions'] as $prediction) {
            $sources[] = [
                'type' => 'client',
                'id' => (int) $prediction['client_id'],
                'label' => sprintf(
                    '%s · %s · score %.2f',
                    $prediction['client_name'],
                    $prediction['routine_type_name'] ?? $prediction['service_type_name'] ?? 'servicio',
                    $prediction['score'],
                ),
            ];
        }

        return ['data' => $result, 'sources' => $sources];
    }
}
