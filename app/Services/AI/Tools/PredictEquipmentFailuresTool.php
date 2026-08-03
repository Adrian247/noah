<?php

namespace App\Services\AI\Tools;

use App\Services\AI\Contracts\AiTool;
use App\Services\Predictive\PredictiveMaintenanceService;

/**
 * Tool de predicción de fallas para el asistente.
 *
 * Un solo punto de consulta para las tres preguntas que la operación hace de verdad: qué equipo
 * está por fallar, cómo va un conjunto (clase, sitio, lista de tags) y qué equipos están en riesgo
 * por un modo de falla concreto.
 */
class PredictEquipmentFailuresTool implements AiTool
{
    public function __construct(private readonly PredictiveMaintenanceService $service) {}

    public function name(): string
    {
        return 'predict_equipment_failures';
    }

    public function description(): string
    {
        return 'Predice riesgo de falla solo con rutinas de línea mantenimiento aplicadas a activos. '
            .'No uses esta tool para manufactura o suministro a cliente (usa predict_client_demand). '
            .'Filtra por tags, clase (scooptram, camión, jumbo…), sitio o modo de falla. '
            .'Antes de llamarla, asegúrate de tener tag, clase o flota explícita del usuario. '
            .'Devuelve probabilidad, fallas esperadas, riesgo, modos probables y factores explicables. Solo lectura.';
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
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Etiquetas de equipo, p. ej. ["SS-305", "JB-101"]. Vacío = flota con rutinas validadas.',
                ],
                'equipment_class' => [
                    'type' => 'string',
                    'description' => 'Clase funcional. Acepta el nombre o el prefijo del tag: SCOOPTRAM/SS, '
                        .'CAMION_BAJO_PERFIL/VQ, JUMBO/JB, QUEBRADORA, MOLINO, CRIBA, BANDA_TRANSPORTADORA, BOMBA…',
                ],
                'site_id' => [
                    'type' => 'integer',
                    'description' => 'Restringe a un sitio.',
                ],
                'failure_mode' => [
                    'type' => 'string',
                    'description' => 'Código, nombre o sistema del modo de falla, p. ej. "FUGA_HIDRAULICA", "transmisión", "motor diésel".',
                ],
                'horizon_days' => [
                    'type' => 'integer',
                    'description' => 'Ventana de predicción en días: 7, 14 o 30. Default 14.',
                ],
                'min_probability' => [
                    'type' => 'number',
                    'description' => 'Deja solo equipos con probabilidad mayor o igual a este valor (0-1).',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Máximo de equipos devueltos (1-50). Default 10.',
                ],
                'as_of' => [
                    'type' => 'string',
                    'description' => 'Fecha de corte YYYY-MM-DD. Default hoy. Útil para reproducir una predicción histórica.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, int $companyId): array
    {
        $result = $this->service->predict($companyId, [
            'tags' => array_values(array_filter(array_map('strval', (array) ($arguments['tags'] ?? [])))),
            'equipment_class' => $arguments['equipment_class'] ?? null,
            'site_id' => isset($arguments['site_id']) ? (int) $arguments['site_id'] : null,
            'failure_mode' => $arguments['failure_mode'] ?? null,
            'horizon_days' => isset($arguments['horizon_days']) ? (int) $arguments['horizon_days'] : null,
            'min_probability' => isset($arguments['min_probability']) ? (float) $arguments['min_probability'] : null,
            'limit' => max(1, min(50, (int) ($arguments['limit'] ?? 10))),
            'as_of' => $arguments['as_of'] ?? null,
        ]);

        $sources = [];
        foreach ($result['predictions'] as $prediction) {
            $sources[] = [
                'type' => 'asset',
                'id' => (int) $prediction['asset_id'],
                'label' => sprintf(
                    '%s · riesgo %s (%.0f %%)',
                    $prediction['tag'] ?? 'Activo #'.$prediction['asset_id'],
                    $prediction['risk_level'],
                    $prediction['probability'] * 100,
                ),
            ];
        }

        return ['data' => $this->summarize($result), 'sources' => $sources];
    }

    /**
     * El asistente responde mejor con menos campos y con el porqué a la vista, así que se recorta
     * la carga cruda del motor a lo que sirve para explicar y decidir.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function summarize(array $result): array
    {
        $predictions = array_map(fn (array $p) => [
            'asset_id' => $p['asset_id'],
            'tag' => $p['tag'],
            'equipment_class' => $p['equipment_class'],
            'risk_level' => $p['risk_level'],
            'probability' => $p['probability'],
            'expected_failures' => $p['expected_failures'] ?? null,
            'expected_downtime_hours' => $p['expected_downtime_hours'],
            'confidence' => $p['confidence'],
            'top_failure_mode' => $p['top_failure_mode'],
            'matched_failure_mode' => $p['matched_failure_mode']['name'] ?? null,
            'likely_failure_modes' => array_map(fn (array $m) => [
                'code' => $m['code'],
                'name' => $m['name'],
                'share' => $m['share'],
                'historical_count' => $m['historical_count'],
            ], array_slice($p['failure_modes'] ?? [], 0, 3)),
            'why' => array_map(fn (array $d) => $d['evidence'], array_slice($p['drivers'] ?? [], 0, 4)),
        ], $result['predictions']);

        return [
            'as_of' => $result['as_of'],
            'horizon_days' => $result['horizon_days'],
            'evaluated_assets' => $result['evaluated_assets'],
            'risk_summary' => $result['risk_summary'] ?? null,
            'model' => $result['model'],
            'scale' => [
                'probability' => 'Probabilidad de al menos una falla correctiva dentro de la ventana.',
                'expected_failures' => 'Fallas esperadas en la ventana; ordena mejor que la probabilidad en flotas de alta tasa.',
                'risk_level' => 'critical ≥ 1 falla esperada, high ≥ 0.4, medium ≥ 0.15, low por debajo.',
                'confidence' => 'Respaldo de historial de la predicción, no su probabilidad.',
            ],
            'predictions' => $predictions,
            'notes' => $result['notes'] ?? null,
        ];
    }
}
