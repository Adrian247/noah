<?php

namespace App\Services\Predictive;

use App\Models\FailurePrediction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cliente del subproyecto ML (`ml/phoenix-predict`).
 *
 * El servicio ML refina la probabilidad con el modelo entrenado; el motor determinístico sigue
 * siendo la fuente de los factores explicativos y el respaldo. Si el servicio no está
 * configurado, tarda o responde algo inesperado, se devuelve `null` y el llamador se queda con
 * la predicción heurística: la función nunca falla hacia el usuario.
 */
class PredictionServiceClient
{
    public function enabled(): bool
    {
        return (bool) config('phoenix.predictive.ml.enabled') && $this->baseUrl() !== '';
    }

    /**
     * Reevalúa un lote de predicciones heurísticas con el modelo entrenado.
     *
     * @param  list<array<string, mixed>>  $predictions
     * @param  list<array<string, mixed>>  $features
     * @return list<array<string, mixed>>|null
     */
    public function score(array $predictions, array $features, int $horizonDays): ?array
    {
        if (! $this->enabled() || $predictions === []) {
            return null;
        }

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->timeout((int) config('phoenix.predictive.ml.timeout', 8))
                ->acceptJson()
                ->asJson()
                ->post('/predict', [
                    'horizon_days' => $horizonDays,
                    'features' => $features,
                    'heuristic' => array_map(
                        fn (array $prediction) => [
                            'asset_id' => $prediction['asset_id'],
                            'probability' => $prediction['probability'],
                        ],
                        $predictions,
                    ),
                ]);

            if (! $response->successful()) {
                return null;
            }

            $scored = $response->json('predictions');
            if (! is_array($scored) || $scored === []) {
                return null;
            }

            return $this->merge($predictions, $scored, (string) ($response->json('model_version') ?? 'ml'));
        } catch (Throwable $e) {
            Log::warning('Servicio predictivo ML no disponible, se usa el motor determinístico.', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** @return array<string, mixed>|null */
    public function health(): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $response = Http::baseUrl($this->baseUrl())->timeout(3)->acceptJson()->get('/health');

            return $response->successful() ? (array) $response->json() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $predictions
     * @param  array<int|string, mixed>  $scored
     * @return list<array<string, mixed>>
     */
    private function merge(array $predictions, array $scored, string $modelVersion): array
    {
        $byAsset = [];
        foreach ($scored as $row) {
            if (is_array($row) && isset($row['asset_id'], $row['probability'])) {
                $byAsset[(int) $row['asset_id']] = (float) $row['probability'];
            }
        }

        foreach ($predictions as $index => $prediction) {
            $probability = $byAsset[(int) $prediction['asset_id']] ?? null;
            if ($probability === null) {
                continue;
            }

            $probability = round(min(0.99, max(0.001, $probability)), 4);
            // El modelo entrega probabilidad; el valor esperado se reconstruye para que el nivel de
            // riesgo se calcule igual que en el motor determinístico.
            $expectedFailures = FailurePrediction::expectedFailuresFromProbability($probability);

            $predictions[$index]['probability'] = $probability;
            $predictions[$index]['expected_failures'] = $expectedFailures;
            $predictions[$index]['risk_level'] = FailurePrediction::riskLevelFor($expectedFailures);
            $predictions[$index]['heuristic_probability'] = $prediction['probability'];
            $predictions[$index]['model_kind'] = 'ml';
            $predictions[$index]['model_version'] = $modelVersion;

            // Los modos se reparten sobre el total del equipo: hay que reescalarlos.
            foreach ($predictions[$index]['failure_modes'] ?? [] as $modeIndex => $mode) {
                $share = (float) $mode['share'];
                $predictions[$index]['failure_modes'][$modeIndex]['probability'] = round($probability * $share, 4);
                $predictions[$index]['failure_modes'][$modeIndex]['expected_failures'] = round($expectedFailures * $share, 4);
            }
        }

        return $predictions;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('phoenix.predictive.ml.url'), '/');
    }
}
