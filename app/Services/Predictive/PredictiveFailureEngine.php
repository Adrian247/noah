<?php

namespace App\Services\Predictive;

use App\Enums\RoutineStatus;
use App\Models\Asset;
use App\Models\FailureMode;
use App\Models\FailurePrediction;
use App\Support\Predictive\EquipmentClass;
use App\Support\Predictive\FailureModeCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Motor predictivo determinístico.
 *
 * Modelo de peligro (hazard) exponencial sobre horas de operación, corregido por factores
 * observables. Es la capa que siempre responde: no necesita servicio externo, funciona con
 * historial corto y explica cada punto de riesgo con el dato que lo provocó.
 *
 * Se reportan dos magnitudes: el número esperado de fallas en la ventana y la probabilidad de que
 * ocurra al menos una.
 *
 *     E = λ_ajustada · horas_esperadas_en_la_ventana
 *     p = 1 - exp(-E)
 *     λ_ajustada = λ_activo · Π factores
 *
 * El nivel de riesgo se decide con E y no con p, porque en flotas de alta tasa —como los scooptram
 * de estas bitácoras, que se intervienen cada pocos días— p llega a 0.97 para casi todo el parque
 * y deja de ordenar. E sigue separando al equipo delicado del que ya está en el taller.
 *
 * λ_activo se estima con contracción bayesiana simple: la tasa propia del activo se mezcla con
 * la de su clase, y la de la clase con la de la empresa. Así un equipo con dos turnos de
 * historial no produce una tasa absurda.
 */
class PredictiveFailureEngine
{
    public const MODEL_KIND = 'heuristic';

    public const MODEL_VERSION = 'hazard-v2';

    /** @var array<string, float> pesos aprendidos por código de driver (default 1.0) */
    private array $driverWeights = [];

    /** Multiplicador global de tasa base aprendido en entrenamiento. */
    private float $globalHazardMultiplier = 1.0;

    /**
     * Aplica calibración publicada en PredictiveAlgorithmVersion.calibration.
     *
     * @param  array<string, mixed>|null  $calibration
     */
    public function withCalibration(?array $calibration): self
    {
        $clone = clone $this;
        $clone->driverWeights = [];
        $clone->globalHazardMultiplier = 1.0;
        if (! is_array($calibration)) {
            return $clone;
        }
        $clone->globalHazardMultiplier = max(0.5, min(2.5, (float) ($calibration['global_hazard_multiplier'] ?? 1.0)));
        $weights = $calibration['driver_weights'] ?? [];
        if (is_array($weights)) {
            foreach ($weights as $code => $weight) {
                $clone->driverWeights[(string) $code] = max(0.5, min(2.0, (float) $weight));
            }
        }

        return $clone;
    }

    /** Horas de pseudo-observación para contraer la tasa del activo hacia la de su clase. */
    private const ASSET_PRIOR_HOURS = 300.0;

    /** Horas de pseudo-observación para contraer la tasa de la clase hacia la de la empresa. */
    private const CLASS_PRIOR_HOURS = 1000.0;

    /** Tasa por hora de operación cuando no hay ningún historial (≈ 1 falla cada 800 h). */
    private const DEFAULT_HAZARD = 1 / 800;

    private const MAX_PROBABILITY = 0.97;

    private const MIN_PROBABILITY = 0.01;

    /** @var array<int, array<string, mixed>> */
    private array $baselineCache = [];

    /** @var array<int, Collection<int, FailureMode>> */
    private array $modeCache = [];

    /** @var array<string, array<string, int>> historial de modos por empresa y clase */
    private array $classHistoryCache = [];

    /**
     * @param  array<string, mixed>  $features
     * @param  array{equipment_class?: string|null, tag?: string|null, name?: string|null}  $context
     * @return array<string, mixed>
     */
    public function predict(int $companyId, array $features, array $context = [], int $horizonDays = 14): array
    {
        $equipmentClass = $context['equipment_class'] ?? null;
        $baseline = $this->baseline($companyId);

        $hazard = $this->assetHazard($features, $equipmentClass, $baseline) * $this->globalHazardMultiplier;
        $drivers = $this->drivers($features);

        $multiplier = 1.0;
        foreach ($drivers as $i => $driver) {
            $baseCode = explode(':', (string) $driver['code'])[0];
            $weight = $this->driverWeights[$driver['code']] ?? $this->driverWeights[$baseCode] ?? 1.0;
            $factor = 1 + max(0, ($driver['factor'] - 1) * $weight);
            $drivers[$i]['factor'] = $factor;
            $multiplier *= $factor;
        }

        $dailyHours = max(0.5, (float) ($features['daily_operating_hours'] ?? 0));
        $exposureHours = $dailyHours * $horizonDays;

        // El valor esperado es la magnitud que ordena la flota; la probabilidad se deriva de él.
        $expectedFailures = round($hazard * $multiplier * $exposureHours, 4);
        $probability = round(
            min(self::MAX_PROBABILITY, max(self::MIN_PROBABILITY, 1 - exp(-$expectedFailures))),
            4,
        );

        $modes = $this->distributeAcrossModes(
            $companyId,
            $features,
            $equipmentClass,
            $probability,
            $expectedFailures,
            $drivers,
        );
        $topMode = $modes[0] ?? null;

        return [
            'asset_id' => (int) ($features['asset_id'] ?? 0),
            'tag' => $context['tag'] ?? null,
            'name' => $context['name'] ?? null,
            'equipment_class' => $equipmentClass,
            'horizon_days' => $horizonDays,
            'probability' => $probability,
            'expected_failures' => $expectedFailures,
            'risk_level' => FailurePrediction::riskLevelFor($expectedFailures),
            'expected_downtime_hours' => $this->expectedDowntime($features, $modes),
            'baseline_hazard_per_hour' => round($hazard, 6),
            'risk_multiplier' => round($multiplier, 3),
            'exposure_hours' => round($exposureHours, 2),
            'confidence' => $this->confidence($features),
            'drivers' => $this->rankDrivers($drivers, $multiplier),
            'failure_modes' => $modes,
            'top_failure_mode' => $topMode === null ? null : [
                'code' => $topMode['code'],
                'name' => $topMode['name'],
                'failure_mode_id' => $topMode['failure_mode_id'],
            ],
            'model_kind' => self::MODEL_KIND,
            'model_version' => self::MODEL_VERSION,
        ];
    }

    /**
     * @param  array<string, mixed>  $features
     * @param  array<string, mixed>  $baseline
     */
    private function assetHazard(array $features, ?string $equipmentClass, array $baseline): float
    {
        $companyRate = (float) ($baseline['company_rate'] ?: self::DEFAULT_HAZARD);

        $classStats = $baseline['classes'][(string) EquipmentClass::canonical($equipmentClass)] ?? null;
        $classRate = $classStats === null
            ? $companyRate
            : ($classStats['failures'] + $companyRate * self::CLASS_PRIOR_HOURS)
                / ($classStats['hours'] + self::CLASS_PRIOR_HOURS);

        $assetHours = (float) ($features['worked_hours_total'] ?? 0);
        $assetFailures = (int) ($features['failures_total'] ?? 0);

        $rate = ($assetFailures + $classRate * self::ASSET_PRIOR_HOURS)
            / ($assetHours + self::ASSET_PRIOR_HOURS);

        return max(1e-6, $rate);
    }

    /**
     * Tasas base de la empresa y de cada clase de equipo.
     *
     * @return array{company_rate: float, classes: array<string, array{failures: int, hours: float}>}
     */
    private function baseline(int $companyId): array
    {
        if (isset($this->baselineCache[$companyId])) {
            return $this->baselineCache[$companyId];
        }

        $classByAsset = $this->assetClasses($companyId);

        $shiftHours = DB::table('equipment_shift_logs')
            ->where('company_id', $companyId)
            ->groupBy('asset_id')
            ->selectRaw('asset_id, COALESCE(SUM(worked_hours), 0) as hours')
            ->pluck('hours', 'asset_id');

        // Exposición desde servicios aplicados (fuente de producto) cuando no hay bitácora.
        $routineHours = DB::table('routines as r')
            ->leftJoin('routine_executions as e', function ($join) {
                $join->on('e.routine_id', '=', 'r.id')
                    ->whereRaw('e.id = (select max(id) from routine_executions where routine_id = r.id)');
            })
            ->where('r.company_id', $companyId)
            ->whereIn('r.status', [
                RoutineStatus::Validated->value,
                RoutineStatus::PendingBilling->value,
                RoutineStatus::Invoiced->value,
            ])
            ->groupBy('r.asset_id')
            ->selectRaw('r.asset_id, COALESCE(SUM(e.duration_minutes), 0) / 60.0 as hours')
            ->pluck('hours', 'asset_id');

        $hoursByAsset = [];
        foreach ($classByAsset as $assetId => $_class) {
            $fromShift = (float) ($shiftHours[$assetId] ?? 0);
            $fromRoutine = (float) ($routineHours[$assetId] ?? 0);
            $hours = $fromShift > 0 ? $fromShift : $fromRoutine;
            if ($hours > 0) {
                $hoursByAsset[$assetId] = $hours;
            }
        }

        $failuresByAsset = DB::table('equipment_failures')
            ->where('company_id', $companyId)
            ->where('maintenance_type', 'corrective')
            ->groupBy('asset_id')
            ->selectRaw('asset_id, COUNT(*) as total')
            ->pluck('total', 'asset_id');

        $classes = [];
        $totalHours = 0.0;
        $totalFailures = 0;

        foreach ($hoursByAsset as $assetId => $hours) {
            $class = $classByAsset[(int) $assetId] ?? 'OTRO';
            $failures = (int) ($failuresByAsset[$assetId] ?? 0);
            $classes[$class] ??= ['failures' => 0, 'hours' => 0.0];
            $classes[$class]['failures'] += $failures;
            $classes[$class]['hours'] += (float) $hours;
            $totalHours += (float) $hours;
            $totalFailures += $failures;
        }

        return $this->baselineCache[$companyId] = [
            'company_rate' => $totalHours > 0 ? $totalFailures / $totalHours : self::DEFAULT_HAZARD,
            'classes' => $classes,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function assetClasses(int $companyId): array
    {
        return Asset::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->get(['id', 'tag', 'metadata'])
            ->mapWithKeys(fn (Asset $asset) => [(int) $asset->id => $asset->equipmentClass() ?? 'OTRO'])
            ->all();
    }

    /**
     * Factores observables que multiplican la tasa base.
     *
     * Cada factor es ≥ 1: el motor solo sube el riesgo sobre la línea base con evidencia. Los
     * pesos son deliberadamente conservadores y están documentados para poder calibrarlos contra
     * el histórico sin cambiar la estructura.
     *
     * @param  array<string, mixed>  $features
     * @return list<array{code: string, label: string, factor: float, evidence: string, signals: list<string>}>
     */
    private function drivers(array $features): array
    {
        $drivers = [];

        $oilRatio = $features['oil_rate_ratio'] ?? null;
        if ($oilRatio !== null && $oilRatio >= 1.25) {
            $drivers[] = [
                'code' => 'consumo_aceite',
                'label' => 'Consumo de aceite por encima de su línea base',
                'factor' => 1 + min(1.5, ($oilRatio - 1.25) * 2.0),
                'evidence' => sprintf(
                    'Consume %s L/h en 7 días contra %s L/h de línea base de 90 días (%.0f %% más).',
                    $features['oil_per_hour_7d'],
                    $features['oil_per_hour_90d'],
                    ($oilRatio - 1) * 100,
                ),
                'signals' => ['consumo_aceite'],
            ];
        }

        $coolantRatio = $features['coolant_rate_ratio'] ?? null;
        if ($coolantRatio !== null && $coolantRatio >= 1.3) {
            $drivers[] = [
                'code' => 'consumo_refrigerante',
                'label' => 'Consumo de refrigerante elevado',
                'factor' => 1 + min(0.9, ($coolantRatio - 1.3) * 1.5),
                'evidence' => sprintf('Consumo de refrigerante %.0f %% arriba de su línea base.', ($coolantRatio - 1) * 100),
                'signals' => ['consumo_refrigerante'],
            ];
        }

        $dieselRatio = $features['diesel_rate_ratio'] ?? null;
        if ($dieselRatio !== null && $dieselRatio >= 1.2) {
            $drivers[] = [
                'code' => 'consumo_diesel',
                'label' => 'Consumo de diésel elevado',
                'factor' => 1 + min(0.6, ($dieselRatio - 1.2) * 1.2),
                'evidence' => sprintf('Consumo de diésel %.0f %% arriba de su línea base.', ($dieselRatio - 1) * 100),
                'signals' => ['consumo_diesel'],
            ];
        }

        $alarms = (int) ($features['alarms_7d'] ?? 0);
        $warnings = (int) ($features['warnings_7d'] ?? 0);
        $alarmScore = $alarms + $warnings * 0.35;
        if ($alarmScore >= 3) {
            $drivers[] = [
                'code' => 'alarmas_recientes',
                'label' => 'Alarmas del control en los últimos 7 días',
                'factor' => 1 + min(1.2, $alarmScore / 20),
                'evidence' => sprintf('%d alarmas y %d advertencias en 7 días.', $alarms, $warnings),
                'signals' => ['alarma_plc'],
            ];
        }

        // Una alarma que reaparece varios días es señal de degradación, no de un incidente aislado.
        foreach ((array) ($features['event_codes_7d'] ?? []) as $code => $event) {
            if ((int) ($event['days'] ?? 0) < 3) {
                continue;
            }
            $drivers[] = [
                'code' => 'alarma_recurrente:'.$code,
                'label' => 'Alarma recurrente '.$code,
                'factor' => $event['severity'] === 'alarm' ? 1.45 : 1.3,
                'evidence' => sprintf(
                    '%s (%s) apareció en %d de los últimos 7 días, %d veces.',
                    $code,
                    $event['name'],
                    $event['days'],
                    $event['occurrences'],
                ),
                'signals' => ['alarma_plc', 'evento:'.$code],
            ];
        }

        $compliance = $features['pm_compliance_90d'] ?? null;
        if ($compliance !== null && $compliance < 0.8) {
            $drivers[] = [
                'code' => 'incumplimiento_preventivo',
                'label' => 'Preventivo no ejecutado',
                'factor' => 1 + min(1.2, (0.8 - $compliance) * 1.5),
                'evidence' => sprintf(
                    'Cumplimiento de servicios preventivos al %.0f %% en 90 días, con %d pendientes.',
                    $compliance * 100,
                    (int) ($features['pm_backlog_90d'] ?? 0),
                ),
                'signals' => ['cumplimiento_pm', 'servicios'],
            ];
        }

        $daysSinceRoutine = $features['days_since_last_routine'] ?? null;
        if ($daysSinceRoutine !== null && $daysSinceRoutine >= 21) {
            $drivers[] = [
                'code' => 'servicio_atrasado',
                'label' => 'Sin servicio de mantenimiento reciente',
                'factor' => 1 + min(0.8, ($daysSinceRoutine - 21) / 60),
                'evidence' => sprintf(
                    'Último servicio aplicado hace %d días%s.',
                    (int) $daysSinceRoutine,
                    isset($features['last_routine_at']) ? ' ('.$features['last_routine_at'].')' : '',
                ),
                'signals' => ['servicios'],
            ];
        }

        // Intensidad de servicios de mantenimiento recientes (hazard-v2).
        $routines7 = (int) ($features['routines_7d'] ?? 0);
        $routines30ForIntensity = (int) ($features['routines_30d'] ?? 0);
        if ($routines7 >= 2 || $routines30ForIntensity >= 4) {
            $intensity = $routines7 * 1.5 + max(0, $routines30ForIntensity - $routines7) * 0.35;
            $drivers[] = [
                'code' => 'intensidad_servicios',
                'label' => 'Alta intensidad de servicios de mantenimiento',
                'factor' => 1 + min(0.9, $intensity / 12),
                'evidence' => sprintf('%d servicios en 7 días · %d en 30 días.', $routines7, $routines30ForIntensity),
                'signals' => ['servicios_mantenimiento'],
            ];
        }

        $backlog = (int) ($features['pm_backlog_90d'] ?? $features['routines_pending'] ?? 0);
        if ($backlog >= 2) {
            $drivers[] = [
                'code' => 'backlog_servicios',
                'label' => 'Backlog de servicios abiertos',
                'factor' => 1 + min(0.7, $backlog / 8),
                'evidence' => sprintf('%d servicios / preventivos pendientes.', $backlog),
                'signals' => ['backlog'],
            ];
        }

        $consumptionSpike = (float) ($features['consumption_qty_30d'] ?? 0);
        $routines30 = (int) ($features['routines_30d'] ?? 0);
        if ($routines30 > 0 && $consumptionSpike / max(1, $routines30) >= 8) {
            $drivers[] = [
                'code' => 'consumo_servicios',
                'label' => 'Consumos elevados en servicios recientes',
                'factor' => 1 + min(0.7, ($consumptionSpike / max(1, $routines30) - 8) / 20),
                'evidence' => sprintf(
                    '%.1f unidades de consumo en %d servicios de 30 días.',
                    $consumptionSpike,
                    $routines30,
                ),
                'signals' => ['consumos', 'servicios'],
            ];
        }

        $trend = $features['availability_trend'] ?? null;
        if ($trend !== null && $trend <= -0.05) {
            $drivers[] = [
                'code' => 'disponibilidad_a_la_baja',
                'label' => 'Disponibilidad en descenso',
                'factor' => 1 + min(0.8, abs($trend) * 4),
                'evidence' => sprintf(
                    'Disponibilidad de 7 días %.1f puntos por debajo de la de 30 días.',
                    abs($trend) * 100,
                ),
                'signals' => ['disponibilidad'],
            ];
        }

        $failures30 = (int) ($features['failures_30d'] ?? 0);
        if ($failures30 >= 2) {
            $drivers[] = [
                'code' => 'correctivo_recurrente',
                'label' => 'Fallas correctivas repetidas',
                'factor' => 1 + min(1.0, ($failures30 - 1) * 0.3),
                'evidence' => sprintf(
                    '%d intervenciones correctivas en 30 días (%.1f h fuera de servicio).',
                    $failures30,
                    (float) ($features['failure_downtime_30d'] ?? 0),
                ),
                'signals' => ['reparacion_imperfecta'],
            ];
        }

        $componentLife = $features['worst_component_life_used'] ?? null;
        if ($componentLife !== null && $componentLife >= 0.8) {
            $drivers[] = [
                'code' => 'vida_componente',
                'label' => 'Componente cerca del fin de vida',
                'factor' => 1 + min(1.5, ($componentLife - 0.8) * 3),
                'evidence' => sprintf('Componente con %.0f %% de su vida esperada consumida.', $componentLife * 100),
                'signals' => ['horometro', 'vida_componente'],
            ];
        }

        $measurementLevel = $features['worst_measurement_level'] ?? null;
        if ($measurementLevel === 'warning' || $measurementLevel === 'critical') {
            $drivers[] = [
                'code' => 'medicion_condicion',
                'label' => 'Medición de condición fuera de rango',
                'factor' => $measurementLevel === 'critical' ? 2.2 : 1.4,
                'evidence' => 'Al menos una medición de condición está en zona '
                    .($measurementLevel === 'critical' ? 'crítica' : 'de alerta').'.',
                'signals' => ['vibracion', 'analisis_aceite', 'termografia'],
            ];
        }

        $hoursSincePreventive = $features['hours_since_last_preventive'] ?? null;
        // Intervalo del plan OEM de su clase; 250 h solo si el equipo no tiene plan de referencia.
        $interval = (float) ($features['oem_service_interval_hours'] ?? 250);
        if ($hoursSincePreventive !== null && $hoursSincePreventive > $interval) {
            $drivers[] = [
                'code' => 'servicio_vencido',
                'label' => 'Servicio por horas vencido',
                'factor' => 1 + min(0.9, ($hoursSincePreventive - $interval) / max(1.0, $interval * 2)),
                'evidence' => sprintf(
                    '%.0f h de operación desde el último preventivo registrado (%s); el intervalo de referencia es %.0f h.',
                    $hoursSincePreventive,
                    $features['last_preventive_on'] ?? 'sin fecha',
                    $interval,
                ),
                'signals' => ['horometro', 'cumplimiento_pm'],
            ];
        }

        return $drivers;
    }

    /**
     * Ordena los factores por su aporte al riesgo, en puntos porcentuales del multiplicador.
     *
     * @param  list<array<string, mixed>>  $drivers
     * @return list<array<string, mixed>>
     */
    private function rankDrivers(array $drivers, float $multiplier): array
    {
        if ($drivers === []) {
            return [];
        }

        $excess = array_map(fn (array $d) => $d['factor'] - 1, $drivers);
        $totalExcess = array_sum($excess) ?: 1.0;

        $ranked = [];
        foreach ($drivers as $index => $driver) {
            $ranked[] = [
                'code' => $driver['code'],
                'label' => $driver['label'],
                'factor' => round($driver['factor'], 3),
                'contribution' => round($excess[$index] / $totalExcess, 4),
                'evidence' => $driver['evidence'],
            ];
        }

        usort($ranked, fn (array $a, array $b) => $b['contribution'] <=> $a['contribution']);

        return $ranked;
    }

    /**
     * Reparte la probabilidad total entre modos de falla.
     *
     * Prior: frecuencia histórica del activo, contraída hacia la de su clase. Luego se refuerzan
     * los modos cuyos precursores o señales están activos ahora mismo.
     *
     * @param  array<string, mixed>  $features
     * @param  list<array<string, mixed>>  $drivers
     * @return list<array<string, mixed>>
     */
    private function distributeAcrossModes(
        int $companyId,
        array $features,
        ?string $equipmentClass,
        float $probability,
        float $expectedFailures,
        array $drivers,
    ): array {
        $modes = $this->modes($companyId)
            ->reject(fn (FailureMode $mode) => in_array($mode->code, FailureModeCatalog::NON_FAILURE_CODES, true))
            ->filter(fn (FailureMode $mode) => $mode->appliesToClass($equipmentClass));

        if ($modes->isEmpty()) {
            return [];
        }

        $assetHistory = (array) ($features['failure_modes_history'] ?? []);
        $classHistory = $this->classModeHistory($companyId, $equipmentClass);
        $activeSignals = array_unique(array_merge(...array_map(
            fn (array $driver) => $driver['signals'],
            $drivers,
        ) ?: [[]]));
        $activeCodes = array_keys((array) ($features['event_codes_7d'] ?? []));

        $weights = [];
        foreach ($modes as $mode) {
            $own = (float) ($assetHistory[$mode->code]['count'] ?? 0);
            $class = (float) ($classHistory[$mode->code] ?? 0);

            // Prior suave: severidad del modo cuando no hay historial de ningún tipo.
            $weight = $own * 3.0 + $class * 1.0 + $mode->severityWeight() * 0.5;

            $matchedCodes = array_intersect((array) $mode->precursor_event_codes, $activeCodes);
            if ($matchedCodes !== []) {
                $weight *= 1 + 0.8 * count($matchedCodes);
            }

            $matchedSignals = array_intersect((array) $mode->monitoring_signals, $activeSignals);
            if ($matchedSignals !== []) {
                $weight *= 1 + 0.35 * count($matchedSignals);
            }

            if ($weight > 0) {
                $weights[$mode->code] = ['mode' => $mode, 'weight' => $weight, 'codes' => array_values($matchedCodes)];
            }
        }

        $total = array_sum(array_column($weights, 'weight'));
        if ($total <= 0) {
            return [];
        }

        $result = [];
        foreach ($weights as $code => $entry) {
            $share = $entry['weight'] / $total;
            $result[] = [
                'failure_mode_id' => (int) $entry['mode']->id,
                'code' => $code,
                'name' => $entry['mode']->name,
                'system' => $entry['mode']->system,
                'severity' => $entry['mode']->severity,
                'share' => round($share, 4),
                'probability' => round($probability * $share, 4),
                'expected_failures' => round($expectedFailures * $share, 4),
                'mean_repair_hours' => $entry['mode']->mean_repair_hours,
                'historical_count' => (int) ($assetHistory[$code]['count'] ?? 0),
                'matched_event_codes' => $entry['codes'],
            ];
        }

        usort($result, fn (array $a, array $b) => $b['share'] <=> $a['share']);

        return array_slice($result, 0, 5);
    }

    /**
     * @return array<string, int>
     */
    private function classModeHistory(int $companyId, ?string $equipmentClass): array
    {
        $target = (string) EquipmentClass::canonical($equipmentClass);
        $cacheKey = $companyId.'|'.$target;
        if (isset($this->classHistoryCache[$cacheKey])) {
            return $this->classHistoryCache[$cacheKey];
        }

        $classByAsset = $this->assetClasses($companyId);
        $assetIds = array_keys(array_filter(
            $classByAsset,
            fn (string $class) => $target === '' || $class === $target,
        ));

        if ($assetIds === []) {
            return $this->classHistoryCache[$cacheKey] = [];
        }

        return $this->classHistoryCache[$cacheKey] = DB::table('equipment_failures as f')
            ->join('failure_modes as m', 'm.id', '=', 'f.failure_mode_id')
            ->where('f.company_id', $companyId)
            ->whereIn('f.asset_id', $assetIds)
            ->where('f.maintenance_type', 'corrective')
            ->groupBy('m.code')
            ->selectRaw('m.code, COUNT(*) as total')
            ->pluck('total', 'code')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $features
     * @param  list<array<string, mixed>>  $modes
     */
    private function expectedDowntime(array $features, array $modes): ?float
    {
        $mttr = $features['mttr_hours'] ?? null;
        if ($modes === []) {
            return $mttr === null ? null : round((float) $mttr, 2);
        }

        $weighted = 0.0;
        $shareTotal = 0.0;
        foreach ($modes as $mode) {
            $hours = $mode['mean_repair_hours'] ?? $mttr;
            if ($hours === null) {
                continue;
            }
            $weighted += (float) $hours * (float) $mode['share'];
            $shareTotal += (float) $mode['share'];
        }

        if ($shareTotal <= 0) {
            return $mttr === null ? null : round((float) $mttr, 2);
        }

        return round($weighted / $shareTotal, 2);
    }

    /**
     * Confianza en la predicción según cuánto historial la respalda (no es la probabilidad).
     *
     * @param  array<string, mixed>  $features
     */
    private function confidence(array $features): float
    {
        $shifts = (int) ($features['shifts_total'] ?? 0);
        $history = min(1.0, $shifts / 60);
        $recency = ($features['days_since_last_log'] ?? 999) <= 7 ? 1.0 : 0.6;
        $signals = 0.0;
        foreach (['event_codes_7d', 'measurements', 'components'] as $key) {
            if (! empty($features[$key])) {
                $signals += 1 / 3;
            }
        }

        return round(min(1.0, 0.45 * $history + 0.3 * $recency + 0.25 * $signals), 3);
    }

    /**
     * @return Collection<int, FailureMode>
     */
    private function modes(int $companyId): Collection
    {
        return $this->modeCache[$companyId] ??= FailureMode::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->get();
    }
}
