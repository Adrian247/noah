<?php

namespace App\Services\Predictive;

use App\Enums\RoutineStatus;
use App\Enums\ServiceCategory;
use App\Models\Asset;
use App\Models\Company;
use App\Models\EquipmentEvent;
use App\Models\EquipmentFailure;
use App\Models\EquipmentShiftLog;
use App\Models\EquipmentWorkOrder;
use App\Models\FailureMode;
use App\Models\FailurePrediction;
use App\Models\PredictiveAlgorithmVersion;
use App\Models\Routine;
use App\Support\Predictive\EquipmentClass;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fachada de mantenimiento predictivo: selecciona equipos, predice y persiste el resultado.
 *
 * Es el único punto de entrada para las tools del asistente y los endpoints, de forma que la
 * consulta por equipo, por conjunto, por clase, por sitio o por modo de falla se resuelva igual
 * en todos lados.
 */
class PredictiveMaintenanceService
{
    /** Límite duro de equipos evaluados por consulta, para acotar el costo. */
    public const MAX_ASSETS = 200;

    /** Días de historia mínima antes de la primera fecha de corte de un activo. */
    private const WARMUP_DAYS = 7;

    public function __construct(
        private readonly FeatureBuilder $features,
        private readonly PredictiveFailureEngine $engine,
        private readonly PredictionServiceClient $mlClient,
    ) {}

    /**
     * Predice riesgo de falla para el conjunto de equipos que cumpla el filtro.
     *
     * @param  array{
     *     asset_ids?: list<int>,
     *     tags?: list<string>,
     *     equipment_class?: string|null,
     *     site_id?: int|null,
     *     failure_mode?: string|null,
     *     horizon_days?: int,
     *     min_probability?: float|null,
     *     limit?: int,
     *     persist?: bool,
     *     as_of?: string|null
     * }  $filters
     * @return array<string, mixed>
     */
    public function predict(int $companyId, array $filters = []): array
    {
        $horizonDays = $this->normalizeHorizon($filters['horizon_days'] ?? null);
        $asOf = isset($filters['as_of']) && $filters['as_of']
            ? CarbonImmutable::parse($filters['as_of'])
            : $this->defaultAsOf($companyId);
        $limit = max(1, min(self::MAX_ASSETS, (int) ($filters['limit'] ?? 20)));

        $assets = $this->resolveAssets($companyId, $filters);
        if ($assets->isEmpty()) {
            $latestRoutine = $this->latestValidatedRoutineAt($companyId);

            $notes = ['No hay activos con servicios de mantenimiento aplicados que coincidan con el filtro.'];
            if ($latestRoutine === null) {
                $notes = [
                    'Esta empresa aún no tiene servicios de mantenimiento validados sobre activos. '
                    .'Aplica y valida servicios de mantenimiento sobre equipos para poder predecir.',
                ];
            }

            return [
                'as_of' => $asOf->toDateString(),
                'horizon_days' => $horizonDays,
                'evaluated_assets' => 0,
                'returned_assets' => 0,
                'model' => $this->modelDescriptor($companyId, false),
                'risk_summary' => ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0],
                'predictions' => [],
                'data_coverage' => [
                    'latest_validated_routine' => $latestRoutine,
                    'assets_with_routines' => 0,
                    'feature_source' => 'routines',
                ],
                'notes' => $notes,
            ];
        }

        $assetIds = $assets->pluck('id')->map(fn ($id) => (int) $id)->all();
        $featureSets = $this->features->forAssets($companyId, $assetIds, $asOf);
        $engine = $this->calibratedEngine($companyId);

        $predictions = [];
        foreach ($assets as $asset) {
            $assetFeatures = $featureSets[(int) $asset->id] ?? ['asset_id' => (int) $asset->id];
            $predictions[] = $engine->predict(
                $companyId,
                $assetFeatures,
                [
                    'tag' => $asset->tag,
                    'name' => $asset->metadata['name'] ?? $asset->tag,
                    'equipment_class' => $asset->equipmentClass(),
                ],
                $horizonDays,
            );
        }

        $refined = $this->mlClient->score($predictions, $featureSets, $horizonDays);
        $usedMl = $refined !== null;
        $predictions = $refined ?? $predictions;

        $predictions = $this->applyFailureModeFilter($predictions, $filters['failure_mode'] ?? null);

        $minProbability = $filters['min_probability'] ?? null;
        if ($minProbability !== null) {
            $predictions = array_values(array_filter(
                $predictions,
                fn (array $p) => $p['probability'] >= (float) $minProbability,
            ));
        }

        // Se ordena por número esperado de fallas: la probabilidad se satura y empata a casi todos.
        usort($predictions, fn (array $a, array $b) => [$b['expected_failures'], $b['probability']]
            <=> [$a['expected_failures'], $a['probability']]);
        $ranked = array_slice($predictions, 0, $limit);

        if ($filters['persist'] ?? true) {
            $this->persist($companyId, $ranked, $asOf, $horizonDays, $featureSets);
        }

        return [
            'as_of' => $asOf->toDateString(),
            'horizon_days' => $horizonDays,
            'evaluated_assets' => count($assetIds),
            'returned_assets' => count($ranked),
            'model' => $this->modelDescriptor($companyId, $usedMl, $ranked[0]['model_version'] ?? null),
            'risk_summary' => $this->riskSummary($predictions),
            'predictions' => $ranked,
            'data_coverage' => [
                'latest_validated_routine' => $this->latestValidatedRoutineAt($companyId),
                'assets_with_routines' => count($assetIds),
                'feature_source' => 'routines',
            ],
        ];
    }

    /**
     * Ficha de salud de un equipo: lo que sustenta la predicción y permite auditarla.
     *
     * @return array<string, mixed>
     */
    public function health(int $companyId, Asset $asset, ?string $asOf = null): array
    {
        $date = $asOf ? CarbonImmutable::parse($asOf) : $this->defaultAsOf($companyId);
        $features = $this->features->forAssets($companyId, [(int) $asset->id], $date)[(int) $asset->id] ?? [];
        $prediction = $this->calibratedEngine($companyId)->predict($companyId, $features, [
            'tag' => $asset->tag,
            'name' => $asset->metadata['name'] ?? $asset->tag,
            'equipment_class' => $asset->equipmentClass(),
        ], $this->normalizeHorizon(null));

        return [
            'asset' => [
                'id' => (int) $asset->id,
                'tag' => $asset->tag,
                'name' => $asset->metadata['name'] ?? $asset->tag,
                'equipment_class' => $asset->equipmentClass(),
                'manufacturer' => $asset->metadata['manufacturer'] ?? null,
                'model' => $asset->metadata['model'] ?? null,
                'location' => $asset->location_label,
                'site_id' => (int) $asset->site_id,
            ],
            'as_of' => $date->toDateString(),
            'reliability' => [
                'hour_meter' => $features['hour_meter'] ?? null,
                'worked_hours_total' => $features['worked_hours_total'] ?? null,
                'availability_7d' => $features['availability_7d'] ?? null,
                'availability_30d' => $features['availability_30d'] ?? null,
                'utilization_30d' => $features['utilization_30d'] ?? null,
                'mtbf_hours' => $features['mtbf_hours'] ?? null,
                'mttr_hours' => $features['mttr_hours'] ?? null,
                'failures_30d' => $features['failures_30d'] ?? 0,
                'failures_90d' => $features['failures_90d'] ?? 0,
                'days_since_last_failure' => $features['days_since_last_failure'] ?? null,
                'pm_compliance_90d' => $features['pm_compliance_90d'] ?? null,
                'pm_backlog_90d' => $features['pm_backlog_90d'] ?? 0,
                'last_preventive_on' => $features['last_preventive_on'] ?? null,
                'oil_rate_ratio' => $features['oil_rate_ratio'] ?? null,
                'diesel_rate_ratio' => $features['diesel_rate_ratio'] ?? null,
                'coolant_rate_ratio' => $features['coolant_rate_ratio'] ?? null,
            ],
            'prediction' => $prediction,
            'recent_events' => $this->recentEvents($companyId, $asset, $date),
            'recent_failures' => $this->recentFailures($companyId, $asset, $date),
            'pending_work_orders' => $this->pendingWorkOrders($companyId, $asset, $date),
            'components' => $features['components'] ?? [],
            'measurements' => $features['measurements'] ?? [],
        ];
    }

    /**
     * Evalúa predicciones pasadas contra lo que realmente ocurrió: precisión medible.
     *
     * @return array<string, mixed>
     */
    public function evaluateOutcomes(int $companyId, ?string $asOf = null): array
    {
        $now = $asOf ? CarbonImmutable::parse($asOf) : CarbonImmutable::today();

        $pending = FailurePrediction::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->whereNull('outcome_evaluated_at')
            ->get();

        $evaluated = 0;
        $hits = 0;
        foreach ($pending as $prediction) {
            $windowEnd = CarbonImmutable::parse($prediction->predicted_on)->addDays($prediction->horizon_days);
            if ($windowEnd->greaterThan($now)) {
                continue;
            }

            $failed = EquipmentFailure::withoutGlobalScope('company')
                ->where('company_id', $companyId)
                ->where('asset_id', $prediction->asset_id)
                ->where('maintenance_type', 'corrective')
                ->whereBetween('started_at', [
                    CarbonImmutable::parse($prediction->predicted_on)->startOfDay(),
                    $windowEnd->endOfDay(),
                ])
                ->exists();

            $prediction->update([
                'outcome_failed' => $failed,
                'outcome_evaluated_at' => now(),
            ]);

            $evaluated++;
            if ($failed && $prediction->probability >= FailurePrediction::ALERT_PROBABILITY) {
                $hits++;
            }
        }

        return $this->accuracyReport($companyId) + ['newly_evaluated' => $evaluated, 'new_hits' => $hits];
    }

    /**
     * Métricas de acierto acumuladas, tomando `probability >= 0.45` como alerta.
     *
     * @return array<string, mixed>
     */
    public function accuracyReport(int $companyId): array
    {
        $rows = FailurePrediction::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->whereNotNull('outcome_failed')
            ->get(['probability', 'outcome_failed', 'horizon_days']);

        $threshold = FailurePrediction::ALERT_PROBABILITY;

        if ($rows->isEmpty()) {
            return ['evaluated' => 0, 'precision' => null, 'recall' => null, 'alert_threshold' => $threshold];
        }

        $truePositive = $rows->filter(fn ($r) => $r->probability >= $threshold && $r->outcome_failed)->count();
        $falsePositive = $rows->filter(fn ($r) => $r->probability >= $threshold && ! $r->outcome_failed)->count();
        $falseNegative = $rows->filter(fn ($r) => $r->probability < $threshold && $r->outcome_failed)->count();

        return [
            'evaluated' => $rows->count(),
            'alert_threshold' => $threshold,
            'true_positive' => $truePositive,
            'false_positive' => $falsePositive,
            'false_negative' => $falseNegative,
            'precision' => $truePositive + $falsePositive > 0
                ? round($truePositive / ($truePositive + $falsePositive), 4)
                : null,
            'recall' => $truePositive + $falseNegative > 0
                ? round($truePositive / ($truePositive + $falseNegative), 4)
                : null,
        ];
    }

    /**
     * Dataset supervisado para entrenar el modelo: features en la fecha de corte y etiqueta
     * de si el equipo falló dentro de la ventana siguiente.
     *
     * @return array<string, mixed>
     */
    public function trainingDataset(int $companyId, int $horizonDays = 14, int $strideDays = 7): array
    {
        // Cobertura por activo: preferir bitácora de referencia si existe; si no, servicios aplicados.
        // Etiquetar un corte cuya ventana futura cae fuera de la cobertura produce un cero
        // artificial ("no falló" cuando en realidad no hay datos).
        $coverage = $this->assetCoverageSpans($companyId);
        $coverageSource = $coverage['source'];
        $spans = $coverage['spans'];

        if ($spans === []) {
            return [
                'horizon_days' => $horizonDays,
                'rows' => [],
                'total' => 0,
                'positives' => 0,
                'notes' => ['Sin cobertura de servicios ni bitácoras de referencia para etiquetar.'],
            ];
        }

        $first = collect($spans)->min('from');
        $last = collect($spans)->max('to');
        $assetIds = array_keys($spans);

        $episodes = $this->failureEpisodeIndex($companyId);

        $rows = [];
        $positives = 0;
        $censored = 0;
        $outOfCoverage = 0;
        // Se avanza la fecha de corte a saltos para no generar ventanas casi idénticas.
        $cutoff = $first->addDays(self::WARMUP_DAYS);
        while ($cutoff->addDays($horizonDays)->lessThanOrEqualTo($last)) {
            $eligible = [];
            foreach ($assetIds as $assetId) {
                $span = $spans[$assetId];
                $observable = $cutoff->greaterThanOrEqualTo($span['from']->addDays(self::WARMUP_DAYS))
                    && $cutoff->addDays($horizonDays)->lessThanOrEqualTo($span['to']);
                if ($observable) {
                    $eligible[] = $assetId;
                } else {
                    $outOfCoverage++;
                }
            }

            if ($eligible === []) {
                $cutoff = $cutoff->addDays(max(1, $strideDays));

                continue;
            }

            $featureSets = $this->features->forAssets($companyId, $eligible, $cutoff);
            foreach ($featureSets as $assetId => $features) {
                $hasActivity = (int) ($features['shifts_30d'] ?? 0) > 0
                    || (int) ($features['routines_30d'] ?? 0) > 0;
                if (! $hasActivity) {
                    continue;
                }
                // Un equipo que ya está en reparación en la fecha de corte no es predecible: ya
                // falló. Incluirlo enseña la relación al revés, porque acumula todas las señales de
                // falla reciente y su etiqueta es 0 hasta que la reparación termina.
                if ($this->isDownAt($episodes, $assetId, $cutoff)) {
                    $censored++;

                    continue;
                }
                $label = $this->failedWithin($episodes, $assetId, $cutoff, $horizonDays);
                $positives += $label;
                $rows[] = ['label' => $label] + $features;
            }
            $cutoff = $cutoff->addDays(max(1, $strideDays));
        }

        $notes = [];
        if ($rows === []) {
            $coveredDays = $first->diffInDays($last);
            $notes[] = sprintf(
                'La cobertura (%s) abarca %d días (%s a %s) y una ventana de %d días necesita al menos %d: '
                .'no hay ninguna fecha de corte con futuro observable.',
                $coverageSource,
                $coveredDays,
                $first->toDateString(),
                $last->toDateString(),
                $horizonDays,
                self::WARMUP_DAYS + $horizonDays,
            );
        }

        return [
            'company_id' => $companyId,
            'horizon_days' => $horizonDays,
            'stride_days' => $strideDays,
            'coverage_source' => $coverageSource,
            'window' => ['from' => $first->toDateString(), 'to' => $last->toDateString()],
            'rows' => $rows,
            'total' => count($rows),
            'positives' => $positives,
            'censored_in_repair' => $censored,
            'skipped_out_of_coverage' => $outOfCoverage,
            'notes' => $notes,
        ];
    }

    /**
     * @return array{source: string, spans: array<int, array{from: CarbonImmutable, to: CarbonImmutable}>}
     */
    private function assetCoverageSpans(int $companyId): array
    {
        $shiftSpans = EquipmentShiftLog::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->groupBy('asset_id')
            ->selectRaw('asset_id, MIN(logged_on) as first_day, MAX(logged_on) as last_day')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->asset_id => [
                'from' => CarbonImmutable::parse((string) $row->first_day),
                'to' => CarbonImmutable::parse((string) $row->last_day),
            ]])
            ->all();

        if ($shiftSpans !== []) {
            return ['source' => 'shift_logs', 'spans' => $shiftSpans];
        }

        $validated = [
            RoutineStatus::Validated->value,
            RoutineStatus::PendingBilling->value,
            RoutineStatus::Invoiced->value,
        ];

        $routineSpans = DB::table('routines as r')
            ->leftJoin('routine_executions as e', function ($join) {
                $join->on('e.routine_id', '=', 'r.id')
                    ->whereRaw('e.id = (select max(id) from routine_executions where routine_id = r.id)');
            })
            ->where('r.company_id', $companyId)
            ->whereIn('r.status', $validated)
            ->groupBy('r.asset_id')
            ->selectRaw('r.asset_id, MIN(COALESCE(e.validated_at, r.scheduled_at)) as first_day, MAX(COALESCE(e.validated_at, r.scheduled_at)) as last_day')
            ->get()
            ->filter(fn ($row) => $row->first_day !== null && $row->last_day !== null)
            ->mapWithKeys(fn ($row) => [(int) $row->asset_id => [
                'from' => CarbonImmutable::parse((string) $row->first_day)->startOfDay(),
                'to' => CarbonImmutable::parse((string) $row->last_day)->startOfDay(),
            ]])
            ->all();

        return ['source' => 'routines', 'spans' => $routineSpans];
    }

    /**
     * Backtest del motor determinístico sobre el histórico ya cargado.
     *
     * Recorre fechas de corte, predice con lo que se sabía en ese momento y compara contra lo que
     * pasó después. Es la prueba de regresión del modelo: si un cambio en los factores empeora el
     * lift, se ve aquí antes de que llegue a la operación.
     *
     * @return array<string, mixed>
     */
    public function backtest(int $companyId, int $horizonDays = 14, int $strideDays = 7): array
    {
        $dataset = $this->trainingDataset($companyId, $horizonDays, $strideDays);
        if ($dataset['rows'] === []) {
            return ['rows' => 0, 'notes' => ['Sin historial suficiente para backtest.']];
        }

        $context = Asset::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->get(['id', 'tag', 'metadata'])
            ->mapWithKeys(fn (Asset $asset) => [(int) $asset->id => [
                'tag' => $asset->tag,
                'equipment_class' => $asset->equipmentClass(),
            ]])
            ->all();

        $scored = [];
        $engine = $this->calibratedEngine($companyId);
        foreach ($dataset['rows'] as $row) {
            $assetId = (int) $row['asset_id'];
            $prediction = $engine->predict(
                $companyId,
                $row,
                $context[$assetId] ?? [],
                $horizonDays,
            );
            $scored[] = [
                'label' => (int) $row['label'],
                'probability' => (float) $prediction['probability'],
                'expected_failures' => (float) $prediction['expected_failures'],
                'risk_level' => $prediction['risk_level'],
                'equipment_class' => $context[$assetId]['equipment_class'] ?? 'SIN_CLASE',
            ];
        }

        return [
            'horizon_days' => $horizonDays,
            'stride_days' => $strideDays,
            'window' => $dataset['window'],
            'rows' => count($scored),
            'positives' => $dataset['positives'],
            'base_rate' => round($dataset['positives'] / max(1, count($scored)), 4),
            'roc_auc' => $this->rocAuc($scored),
            // La métrica que decide: ordenar dentro de la clase. El AUC global se infla solo con
            // saber que una flota falla más que otra, y eso no ayuda a elegir a qué equipo entrar.
            'roc_auc_by_class' => $this->aucByClass($scored),
            'by_risk_level' => $this->observedRateByLevel($scored),
            'alert_metrics' => $this->alertMetrics($scored, FailurePrediction::ALERT_PROBABILITY),
        ];
    }

    /**
     * @param  list<array{label: int, probability: float, equipment_class: string}>  $scored
     * @return array<string, array{rows: int, base_rate: float, roc_auc: float|null}>
     */
    private function aucByClass(array $scored): array
    {
        $byClass = [];
        foreach ($scored as $row) {
            $byClass[(string) $row['equipment_class']][] = $row;
        }

        $result = [];
        foreach ($byClass as $class => $rows) {
            if (count($rows) < 50) {
                continue;
            }
            $positives = count(array_filter($rows, fn (array $r) => $r['label'] === 1));
            $result[$class] = [
                'rows' => count($rows),
                'base_rate' => round($positives / count($rows), 4),
                'roc_auc' => $this->rocAuc($rows),
            ];
        }

        uasort($result, fn (array $a, array $b) => $b['rows'] <=> $a['rows']);

        return $result;
    }

    /**
     * AUC por el método de rangos de Mann-Whitney, con empates promediados.
     *
     * @param  list<array{label: int, probability: float}>  $scored
     */
    private function rocAuc(array $scored): ?float
    {
        $positives = array_values(array_filter($scored, fn (array $r) => $r['label'] === 1));
        $negatives = array_values(array_filter($scored, fn (array $r) => $r['label'] === 0));
        if ($positives === [] || $negatives === []) {
            return null;
        }

        $wins = 0.0;
        foreach ($positives as $positive) {
            foreach ($negatives as $negative) {
                $wins += match (true) {
                    $positive['probability'] > $negative['probability'] => 1.0,
                    $positive['probability'] === $negative['probability'] => 0.5,
                    default => 0.0,
                };
            }
        }

        return round($wins / (count($positives) * count($negatives)), 4);
    }

    /**
     * Tasa real de falla observada en cada nivel de riesgo: la calibración que importa al usuario.
     *
     * @param  list<array{label: int, risk_level: string}>  $scored
     * @return array<string, array{predicted: int, observed_failures: int, observed_rate: float}>
     */
    private function observedRateByLevel(array $scored): array
    {
        $buckets = [];
        foreach (['critical', 'high', 'medium', 'low'] as $level) {
            $rows = array_values(array_filter($scored, fn (array $r) => $r['risk_level'] === $level));
            if ($rows === []) {
                continue;
            }
            $failures = count(array_filter($rows, fn (array $r) => $r['label'] === 1));
            $buckets[$level] = [
                'predicted' => count($rows),
                'observed_failures' => $failures,
                'observed_rate' => round($failures / count($rows), 4),
            ];
        }

        return $buckets;
    }

    /**
     * @param  list<array{label: int, probability: float}>  $scored
     * @return array<string, float|int|null>
     */
    private function alertMetrics(array $scored, float $threshold): array
    {
        $truePositive = 0;
        $falsePositive = 0;
        $falseNegative = 0;
        foreach ($scored as $row) {
            $alerted = $row['probability'] >= $threshold;
            if ($alerted && $row['label'] === 1) {
                $truePositive++;
            } elseif ($alerted) {
                $falsePositive++;
            } elseif ($row['label'] === 1) {
                $falseNegative++;
            }
        }

        $precision = $truePositive + $falsePositive > 0
            ? round($truePositive / ($truePositive + $falsePositive), 4)
            : null;
        $recall = $truePositive + $falseNegative > 0
            ? round($truePositive / ($truePositive + $falseNegative), 4)
            : null;

        return [
            'threshold' => $threshold,
            'true_positive' => $truePositive,
            'false_positive' => $falsePositive,
            'false_negative' => $falseNegative,
            'precision' => $precision,
            'recall' => $recall,
            'f1' => $precision !== null && $recall !== null && $precision + $recall > 0
                ? round(2 * $precision * $recall / ($precision + $recall), 4)
                : null,
        ];
    }

    /**
     * @return Collection<int, Asset>
     */
    private function resolveAssets(int $companyId, array $filters): Collection
    {
        $query = Asset::withoutGlobalScope('company')->where('company_id', $companyId);

        if (! empty($filters['asset_ids'])) {
            $query->whereIn('id', array_map('intval', (array) $filters['asset_ids']));
        }

        if (! empty($filters['tags'])) {
            $tags = array_map('trim', (array) $filters['tags']);
            $query->where(function ($inner) use ($tags) {
                foreach ($tags as $tag) {
                    $inner->orWhere('tag', 'ilike', $tag);
                }
            });
        }

        if (! empty($filters['site_id'])) {
            $query->where('site_id', (int) $filters['site_id']);
        }

        $assets = $query->orderBy('tag')->limit(self::MAX_ASSETS * 5)->get();

        $class = $filters['equipment_class'] ?? null;
        if ($class !== null && trim((string) $class) !== '') {
            $assets = $assets
                ->filter(fn (Asset $asset) => EquipmentClass::matches($asset->equipmentClass(), (string) $class))
                ->values();
        }

        // Sin filtro explícito, solo equipos con servicios de mantenimiento validadas.
        if (empty($filters['asset_ids']) && empty($filters['tags'])) {
            $withRoutines = Routine::withoutGlobalScope('company')
                ->where('routines.company_id', $companyId)
                ->whereNotNull('routines.asset_id')
                ->whereIn('routines.status', [
                    RoutineStatus::Validated->value,
                    RoutineStatus::PendingBilling->value,
                    RoutineStatus::Invoiced->value,
                ])
                ->whereHas('routineType', fn ($q) => $q->where('service_category', ServiceCategory::Maintenance->value))
                ->distinct()
                ->pluck('asset_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $assets = $assets->whereIn('id', $withRoutines)->values();
        }

        return $assets->take(self::MAX_ASSETS)->values();
    }

    /**
     * Deja solo equipos donde el modo pedido aparece entre los primeros, y lo destaca.
     *
     * @param  list<array<string, mixed>>  $predictions
     * @return list<array<string, mixed>>
     */
    private function applyFailureModeFilter(array $predictions, ?string $failureMode): array
    {
        if ($failureMode === null || trim($failureMode) === '') {
            return $predictions;
        }

        $needle = FailureTextNormalizer::fold($failureMode);
        $filtered = [];
        foreach ($predictions as $prediction) {
            foreach ($prediction['failure_modes'] ?? [] as $mode) {
                $matches = str_contains(FailureTextNormalizer::fold($mode['code']), $needle)
                    || str_contains(FailureTextNormalizer::fold($mode['name']), $needle)
                    || str_contains(FailureTextNormalizer::fold($mode['system']), $needle);
                if (! $matches) {
                    continue;
                }
                $prediction['matched_failure_mode'] = $mode;
                // Se ordena por el riesgo del modo pedido, no por el del equipo completo.
                $prediction['probability'] = $mode['probability'];
                $prediction['expected_failures'] = $mode['expected_failures'];
                $prediction['risk_level'] = FailurePrediction::riskLevelFor($mode['expected_failures']);
                $filtered[] = $prediction;
                break;
            }
        }

        return $filtered;
    }

    /**
     * @param  list<array<string, mixed>>  $predictions
     * @param  array<int, array<string, mixed>>  $featureSets
     */
    private function persist(
        int $companyId,
        array $predictions,
        CarbonImmutable $asOf,
        int $horizonDays,
        array $featureSets,
    ): void {
        if ($predictions === []) {
            return;
        }

        $descriptor = $this->modelDescriptor($companyId, false);
        $algorithmId = $descriptor['algorithm_version_id'] ?? null;

        $payload = [];
        foreach ($predictions as $prediction) {
            $assetId = (int) $prediction['asset_id'];
            $payload[] = [
                'company_id' => $companyId,
                'asset_id' => $assetId,
                'failure_mode_id' => $prediction['top_failure_mode']['failure_mode_id'] ?? null,
                'predicted_on' => $asOf->toDateString(),
                'horizon_days' => $horizonDays,
                'probability' => $prediction['probability'],
                'expected_failures' => $prediction['expected_failures'] ?? null,
                'risk_level' => $prediction['risk_level'],
                'expected_downtime_hours' => $prediction['expected_downtime_hours'],
                'drivers' => json_encode($prediction['drivers']),
                'features' => json_encode($featureSets[$assetId] ?? []),
                'model_kind' => $prediction['model_kind'] ?? PredictiveFailureEngine::MODEL_VERSION,
                'model_version' => $descriptor['version'] ?? $prediction['model_version'],
                'predictive_algorithm_version_id' => $algorithmId,
                'feature_source' => 'routines',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table((new FailurePrediction)->getTable())->upsert(
            $payload,
            ['asset_id', 'predicted_on', 'horizon_days', 'failure_mode_id'],
            [
                'probability', 'expected_failures', 'risk_level', 'expected_downtime_hours',
                'drivers', 'features', 'model_kind', 'model_version',
                'predictive_algorithm_version_id', 'feature_source', 'updated_at',
            ],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $predictions
     * @return array<string, int>
     */
    private function riskSummary(array $predictions): array
    {
        $summary = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($predictions as $prediction) {
            $level = (string) $prediction['risk_level'];
            $summary[$level] = ($summary[$level] ?? 0) + 1;
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function modelDescriptor(int $companyId, bool $usedMl, ?string $version = null): array
    {
        $selected = Company::query()
            ->where('id', $companyId)
            ->with('predictiveAlgorithmVersion')
            ->first();

        $alg = $selected?->predictiveAlgorithmVersion;
        if ($alg === null) {
            $alg = PredictiveAlgorithmVersion::query()
                ->where('status', PredictiveAlgorithmVersion::STATUS_PUBLISHED)
                ->whereIn('kind', [
                    \App\Enums\PredictiveAlgorithmKind::Maintenance->value,
                    \App\Enums\PredictiveAlgorithmKind::LEGACY_MAINTENANCE,
                ])
                ->orderByDesc('published_at')
                ->first();
        }

        return [
            'kind' => $usedMl ? 'ml' : PredictiveFailureEngine::MODEL_VERSION,
            'version' => $alg?->semver ?? $version ?? PredictiveFailureEngine::MODEL_VERSION,
            'algorithm_version_id' => $alg?->id,
            'algorithm_semver' => $alg?->semver,
            'algorithm_kind' => $alg?->kind,
            'ml_model_version' => $usedMl ? $version : null,
            'feature_source' => 'routines',
            'ml_service_enabled' => $this->mlClient->enabled(),
            'ml_service_used' => $usedMl,
        ];
    }

    private function calibratedEngine(int $companyId): PredictiveFailureEngine
    {
        $company = Company::query()->with('predictiveAlgorithmVersion')->find($companyId);
        $alg = $company?->predictiveAlgorithmVersion;
        if ($alg === null) {
            $alg = PredictiveAlgorithmVersion::query()
                ->where('status', PredictiveAlgorithmVersion::STATUS_PUBLISHED)
                ->whereIn('kind', [
                    \App\Enums\PredictiveAlgorithmKind::Maintenance->value,
                    \App\Enums\PredictiveAlgorithmKind::LEGACY_MAINTENANCE,
                ])
                ->orderByDesc('published_at')
                ->first();
        }

        $calibration = is_array($alg?->calibration) ? $alg->calibration : null;

        return $this->engine->withCalibration($calibration);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentEvents(int $companyId, Asset $asset, CarbonImmutable $asOf): array
    {
        return EquipmentEvent::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('asset_id', $asset->id)
            ->whereIn('severity', [EquipmentEvent::SEVERITY_ALARM, EquipmentEvent::SEVERITY_WARNING])
            ->where('occurred_at', '<=', $asOf->endOfDay())
            ->orderByDesc('occurred_at')
            ->limit(10)
            ->get(['code', 'name', 'severity', 'occurrences', 'occurred_at'])
            ->map(fn (EquipmentEvent $event) => [
                'code' => $event->code,
                'name' => $event->name,
                'severity' => $event->severity,
                'occurrences' => $event->occurrences,
                'occurred_at' => $event->occurred_at?->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentFailures(int $companyId, Asset $asset, CarbonImmutable $asOf): array
    {
        return EquipmentFailure::withoutGlobalScope('company')
            ->with('failureMode')
            ->where('company_id', $companyId)
            ->where('asset_id', $asset->id)
            ->where('started_at', '<=', $asOf->endOfDay())
            ->orderByDesc('started_at')
            ->limit(10)
            ->get()
            ->map(fn (EquipmentFailure $failure) => [
                'started_at' => $failure->started_at?->toDateTimeString(),
                'downtime_hours' => $failure->downtime_hours,
                'maintenance_type' => $failure->maintenance_type,
                'failure_mode' => $failure->failureMode?->name,
                'failure_mode_code' => $failure->failureMode?->code,
                'reported_text' => $failure->reported_text,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingWorkOrders(int $companyId, Asset $asset, CarbonImmutable $asOf): array
    {
        return EquipmentWorkOrder::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('asset_id', $asset->id)
            ->whereIn('status', [EquipmentWorkOrder::STATUS_PLANNED, EquipmentWorkOrder::STATUS_SKIPPED])
            ->where('planned_for', '<=', $asOf->toDateString())
            ->orderBy('planned_for')
            ->limit(10)
            ->get(['order_number', 'description', 'planned_for', 'status', 'skip_reason'])
            ->map(fn (EquipmentWorkOrder $order) => [
                'order_number' => $order->order_number,
                'description' => $order->description,
                'planned_for' => $order->planned_for?->toDateString(),
                'status' => $order->status,
                'skip_reason' => $order->skip_reason,
            ])
            ->all();
    }

    /**
     * Episodios de falla por activo, como pares [inicio, fin] en fechas.
     *
     * @return array<int, list<array{from: string, to: string}>>
     */
    private function failureEpisodeIndex(int $companyId): array
    {
        $index = [];
        EquipmentFailure::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('maintenance_type', 'corrective')
            ->orderBy('started_at')
            ->get(['asset_id', 'started_at', 'ended_at'])
            ->each(function (EquipmentFailure $failure) use (&$index) {
                $from = $failure->started_at->toDateString();
                $index[(int) $failure->asset_id][] = [
                    'from' => $from,
                    'to' => $failure->ended_at?->toDateString() ?? $from,
                ];
            });

        return $index;
    }

    /**
     * @param  array<int, list<array{from: string, to: string}>>  $index
     */
    private function isDownAt(array $index, int $assetId, CarbonImmutable $at): bool
    {
        $day = $at->toDateString();
        foreach ($index[$assetId] ?? [] as $episode) {
            if ($episode['from'] <= $day && $day <= $episode['to']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, list<array{from: string, to: string}>>  $index
     */
    private function failedWithin(array $index, int $assetId, CarbonImmutable $from, int $horizonDays): int
    {
        $start = $from->toDateString();
        $end = $from->addDays($horizonDays)->toDateString();
        foreach ($index[$assetId] ?? [] as $episode) {
            if ($episode['from'] > $start && $episode['from'] <= $end) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * Sin fecha explícita usa el último servicio validado. Así la demo con historial reciente
     * no queda vacía por cortar en "hoy" sin actividad.
     */
    private function defaultAsOf(int $companyId): CarbonImmutable
    {
        $latest = $this->latestValidatedRoutineAt($companyId);

        return $latest
            ? CarbonImmutable::parse($latest)
            : CarbonImmutable::today();
    }

    private function latestValidatedRoutineAt(int $companyId): ?string
    {
        $fromExecution = DB::table('routine_executions as e')
            ->join('routines as r', 'r.id', '=', 'e.routine_id')
            ->where('r.company_id', $companyId)
            ->whereNotNull('e.validated_at')
            ->max('e.validated_at');

        $fromRoutine = Routine::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->whereIn('status', [
                RoutineStatus::Validated->value,
                RoutineStatus::PendingBilling->value,
                RoutineStatus::Invoiced->value,
            ])
            ->max('scheduled_at');

        $candidates = array_filter([(string) $fromExecution, (string) $fromRoutine]);
        if ($candidates === []) {
            return null;
        }

        return max($candidates);
    }

    private function normalizeHorizon(?int $horizonDays): int
    {
        $allowed = (array) config('phoenix.predictive.horizons', [7, 14, 30]);
        $default = (int) config('phoenix.predictive.default_horizon_days', 14);
        if ($horizonDays === null) {
            return $default;
        }

        return in_array($horizonDays, array_map('intval', $allowed), true) ? $horizonDays : $default;
    }

    /**
     * Catálogo de modos de falla de la empresa, para la tool de taxonomía.
     *
     * @return list<array<string, mixed>>
     */
    public function failureModes(int $companyId, ?string $equipmentClass = null, ?string $system = null): array
    {
        $query = FailureMode::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->orderBy('sort_order');

        if ($system !== null && trim($system) !== '') {
            $query->where('system', 'ilike', '%'.trim($system).'%');
        }

        return $query->get()
            ->filter(fn (FailureMode $mode) => $mode->appliesToClass($equipmentClass))
            ->map(fn (FailureMode $mode) => [
                'id' => (int) $mode->id,
                'code' => $mode->code,
                'name' => $mode->name,
                'system' => $mode->system,
                'severity' => $mode->severity,
                'equipment_classes' => $mode->equipment_classes,
                'typical_symptoms' => $mode->typical_symptoms,
                'typical_causes' => $mode->typical_causes,
                'monitoring_signals' => $mode->monitoring_signals,
                'precursor_event_codes' => $mode->precursor_event_codes,
                'mean_repair_hours' => $mode->mean_repair_hours,
            ])
            ->values()
            ->all();
    }
}
