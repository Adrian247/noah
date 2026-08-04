<?php

namespace App\Services\Predictive;

use App\Enums\RoutineStatus;
use App\Enums\ServiceCategory;
use App\Models\Asset;
use App\Models\EquipmentEvent;
use App\Support\Predictive\EquipmentClass;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Vector de características por activo a una fecha de corte.
 *
 * Fuente primaria: historial de servicios aplicados (validadas) sobre el activo.
 * Las bitácoras Excel / shift logs son corpus de referencia para entrenamiento, no la
 * operación diaria del producto.
 */
class FeatureBuilder
{
    /** Ventanas en días sobre las que se agregan servicios (y bitácoras de referencia si existen). */
    public const WINDOWS = [7, 30, 90];

    /** Piso para considerar una tarea del plan OEM un servicio y no una ronda de operación. */
    private const MIN_SERVICE_INTERVAL_HOURS = 100;

    /**
     * @param  list<int>  $assetIds
     * @return array<int, array<string, mixed>> Mapa de asset id a características.
     */
    public function forAssets(int $companyId, array $assetIds, ?CarbonImmutable $asOf = null): array
    {
        $assetIds = array_values(array_unique(array_map('intval', $assetIds)));
        if ($assetIds === []) {
            return [];
        }

        $asOf ??= CarbonImmutable::today();
        $features = [];
        foreach ($assetIds as $assetId) {
            $features[$assetId] = [
                'asset_id' => $assetId,
                'as_of' => $asOf->toDateString(),
                'feature_source' => 'routines',
            ];
        }

        $this->applyRoutineHistory($companyId, $assetIds, $asOf, $features);
        $this->applyServiceInterval($companyId, $assetIds, $features);
        // Bitácoras de referencia (si la empresa las tiene) enriquecen; no son requisito.
        $this->applyShiftWindows($companyId, $assetIds, $asOf, $features);
        $this->applyLifetime($companyId, $assetIds, $asOf, $features);
        $this->applyFailures($companyId, $assetIds, $asOf, $features);
        $this->applyEvents($companyId, $assetIds, $asOf, $features);
        $this->applyWorkOrders($companyId, $assetIds, $asOf, $features);
        $this->applyComponentLife($companyId, $assetIds, $asOf, $features);
        $this->applyMeasurements($companyId, $assetIds, $asOf, $features);
        $this->applyDerived($features);

        return $features;
    }

    /**
     * Señales desde servicios validados: frecuencia, rechazo, duración, consumos y comentarios.
     *
     * @param  list<int>  $assetIds
     * @param  array<int, array<string, mixed>>  $features
     */
    private function applyRoutineHistory(int $companyId, array $assetIds, CarbonImmutable $asOf, array &$features): void
    {
        $validated = [
            RoutineStatus::Validated->value,
            RoutineStatus::PendingBilling->value,
            RoutineStatus::Invoiced->value,
        ];

        foreach (self::WINDOWS as $window) {
            $from = $asOf->subDays($window)->startOfDay()->toDateTimeString();
            $to = $asOf->endOfDay()->toDateTimeString();

            $rows = DB::table('routines as r')
                ->join('routine_types as t', 't.id', '=', 'r.routine_type_id')
                ->leftJoin('routine_executions as e', function ($join) {
                    $join->on('e.routine_id', '=', 'r.id')
                        ->whereRaw('e.id = (select max(id) from routine_executions where routine_id = r.id)');
                })
                ->where('r.company_id', $companyId)
                ->whereIn('r.asset_id', $assetIds)
                ->whereNotNull('r.asset_id')
                ->where('t.service_category', ServiceCategory::Maintenance->value)
                ->whereIn('r.status', $validated)
                ->where(function ($q) use ($from, $to) {
                    $q->whereBetween('e.validated_at', [$from, $to])
                        ->orWhere(function ($inner) use ($from, $to) {
                            $inner->whereNull('e.validated_at')
                                ->whereBetween('r.scheduled_at', [$from, $to]);
                        });
                })
                ->groupBy('r.asset_id')
                ->selectRaw(
                    'r.asset_id,
                     COUNT(*) as routines,
                     AVG(e.duration_minutes) as avg_duration,
                     COALESCE(SUM(e.duration_minutes), 0) as duration_minutes,
                     SUM(CASE WHEN e.rejection_reason IS NOT NULL THEN 1 ELSE 0 END) as rejected_once'
                )
                ->get();

            foreach ($rows as $row) {
                $id = (int) $row->asset_id;
                $routines = (int) $row->routines;
                $durationHours = round(((float) $row->duration_minutes) / 60, 2);
                $features[$id]["routines_{$window}d"] = $routines;
                $features[$id]["routine_hours_{$window}d"] = $durationHours;
                $features[$id]["avg_duration_minutes_{$window}d"] = $row->avg_duration === null
                    ? null
                    : round((float) $row->avg_duration, 1);
            }

            $consumptions = DB::table('routine_consumptions as c')
                ->join('routine_executions as e', 'e.id', '=', 'c.routine_execution_id')
                ->join('routines as r', 'r.id', '=', 'e.routine_id')
                ->join('routine_types as t', 't.id', '=', 'r.routine_type_id')
                ->where('r.company_id', $companyId)
                ->whereIn('r.asset_id', $assetIds)
                ->whereNotNull('r.asset_id')
                ->where('t.service_category', ServiceCategory::Maintenance->value)
                ->whereIn('r.status', $validated)
                ->whereBetween('e.validated_at', [$from, $to])
                ->groupBy('r.asset_id')
                ->selectRaw('r.asset_id, COUNT(*) as lines, COALESCE(SUM(c.quantity), 0) as qty')
                ->get();

            foreach ($consumptions as $row) {
                $id = (int) $row->asset_id;
                $features[$id]["consumption_lines_{$window}d"] = (int) $row->lines;
                $features[$id]["consumption_qty_{$window}d"] = round((float) $row->qty, 2);
            }
        }

        $latest = DB::table('routines as r')
            ->join('routine_types as t', 't.id', '=', 'r.routine_type_id')
            ->leftJoin('routine_executions as e', function ($join) {
                $join->on('e.routine_id', '=', 'r.id')
                    ->whereRaw('e.id = (select max(id) from routine_executions where routine_id = r.id)');
            })
            ->where('r.company_id', $companyId)
            ->whereIn('r.asset_id', $assetIds)
            ->whereNotNull('r.asset_id')
            ->where('t.service_category', ServiceCategory::Maintenance->value)
            ->whereIn('r.status', $validated)
            ->where(function ($q) use ($asOf) {
                $q->where('e.validated_at', '<=', $asOf->endOfDay())
                    ->orWhere(function ($inner) use ($asOf) {
                        $inner->whereNull('e.validated_at')
                            ->where('r.scheduled_at', '<=', $asOf->endOfDay());
                    });
            })
            ->groupBy('r.asset_id')
            ->selectRaw(
                'r.asset_id,
                 MAX(COALESCE(e.validated_at, r.scheduled_at)) as last_applied_at,
                 MIN(COALESCE(e.validated_at, r.scheduled_at)) as first_applied_at,
                 COUNT(*) as routines_total,
                 COALESCE(SUM(e.duration_minutes), 0) as duration_minutes_total'
            )
            ->get();

        foreach ($latest as $row) {
            $id = (int) $row->asset_id;
            $last = $row->last_applied_at ? CarbonImmutable::parse((string) $row->last_applied_at) : null;
            $first = $row->first_applied_at ? CarbonImmutable::parse((string) $row->first_applied_at) : null;
            $features[$id]['last_routine_at'] = $last?->toDateString();
            $features[$id]['days_since_last_routine'] = $last
                ? $last->diffInDays($asOf)
                : null;
            $features[$id]['routines_total'] = (int) $row->routines_total;
            $features[$id]['routine_hours_total'] = round(((float) $row->duration_minutes_total) / 60, 2);
            $features[$id]['first_routine_at'] = $first?->toDateString();
            // history_days / worked_hours_total se completan en applyDerived si no hay bitácora.
            // Alias para el factor de servicio del motor heurístico.
            $features[$id]['last_preventive_on'] = $features[$id]['last_preventive_on']
                ?? $features[$id]['last_routine_at'];
            if (($features[$id]['days_since_last_preventive'] ?? null) === null && $last) {
                $features[$id]['days_since_last_preventive'] = $features[$id]['days_since_last_routine'];
            }
            if (($features[$id]['days_since_last_service'] ?? null) === null && $last) {
                $features[$id]['days_since_last_service'] = $features[$id]['days_since_last_routine'];
            }
        }

        $pending = DB::table('routines as r')
            ->join('routine_types as t', 't.id', '=', 'r.routine_type_id')
            ->where('r.company_id', $companyId)
            ->whereIn('r.asset_id', $assetIds)
            ->whereNotNull('r.asset_id')
            ->where('t.service_category', ServiceCategory::Maintenance->value)
            ->whereIn('r.status', [
                RoutineStatus::Assigned->value,
                RoutineStatus::InProgress->value,
                RoutineStatus::PendingValidation->value,
                RoutineStatus::PendingSync->value,
                RoutineStatus::Submitted->value,
            ])
            ->where(function ($q) use ($asOf) {
                $q->whereNull('r.scheduled_at')
                    ->orWhere('r.scheduled_at', '<=', $asOf->endOfDay());
            })
            ->groupBy('r.asset_id')
            ->selectRaw('r.asset_id, COUNT(*) as pending')
            ->pluck('pending', 'asset_id');

        foreach ($pending as $assetId => $count) {
            $features[(int) $assetId]['routines_pending'] = (int) $count;
            $features[(int) $assetId]['pm_backlog_90d'] = (int) $count;
        }

        // Cumplimiento aproximado: servicios validados / (validadas + pendientes) en 90 d.
        foreach ($assetIds as $assetId) {
            $done = (int) ($features[$assetId]['routines_90d'] ?? 0);
            $open = (int) ($features[$assetId]['routines_pending'] ?? 0);
            $denom = $done + $open;
            $features[$assetId]['pm_compliance_90d'] = $denom > 0
                ? round($done / $denom, 4)
                : ($done > 0 ? 1.0 : null);
            $features[$assetId]['corrective_hours_30d'] = $features[$assetId]['corrective_hours_30d']
                ?? (($features[$assetId]['avg_duration_minutes_30d'] ?? 0) > 120
                    ? round(((float) $features[$assetId]['avg_duration_minutes_30d']) / 60, 2)
                    : 0);
        }
    }

    /**
     * Intervalo de servicio de referencia de cada activo, tomado del plan OEM de su clase.
     *
     * Sirve para juzgar "servicio vencido" con la escalera del fabricante en lugar de un número
     * fijo: 250 h en un LHD diésel no significa lo mismo que en una quebradora.
     *
     * @param  list<int>  $assetIds
     * @param  array<int, array<string, mixed>>  $features
     */
    private function applyServiceInterval(int $companyId, array $assetIds, array &$features): void
    {
        $assets = Asset::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->whereIn('id', $assetIds)
            ->with(['catalogItem.oemEquipmentModel:id,manufacturer,model,equipment_class'])
            ->get(['id', 'tag', 'metadata', 'catalog_item_id']);

        // Los intervalos cortos de los planes de planta (8 h, 40 h) son rondas de operador, no
        // servicios: tomarlos como referencia dejaría a toda la planta "con servicio vencido".
        $intervals = DB::table('oem_maintenance_plans as p')
            ->join('oem_maintenance_plan_items as i', 'i.oem_maintenance_plan_id', '=', 'p.id')
            ->where('i.interval_hours', '>=', self::MIN_SERVICE_INTERVAL_HOURS)
            ->groupBy('p.manufacturer', 'p.equipment_class')
            ->selectRaw('p.manufacturer, p.equipment_class, MIN(i.interval_hours) as interval_hours')
            ->get();

        $byManufacturerClass = [];
        $byClass = [];
        foreach ($intervals as $row) {
            $class = (string) EquipmentClass::canonical($row->equipment_class);
            $hours = (int) $row->interval_hours;
            $byManufacturerClass[strtolower((string) $row->manufacturer).'|'.$class] = $hours;
            // Si el fabricante del activo no está en el catálogo, sirve el intervalo de la clase.
            $byClass[$class] = min($byClass[$class] ?? $hours, $hours);
        }

        foreach ($assets as $asset) {
            $oem = $asset->catalogItem?->oemEquipmentModel;
            $class = (string) ($oem?->equipment_class
                ? EquipmentClass::canonical($oem->equipment_class)
                : ($asset->equipmentClass() ?? ''));
            $manufacturer = strtolower(trim((string) (
                $oem?->manufacturer
                ?? $asset->catalogItem?->manufacturer
                ?? $asset->metadata['manufacturer']
                ?? ''
            )));
            $interval = $byManufacturerClass[$manufacturer.'|'.$class]
                ?? $byClass[$class]
                ?? null;

            $features[(int) $asset->id]['equipment_class'] = $class !== '' ? $class : null;
            $features[(int) $asset->id]['oem_equipment_model_id'] = $oem?->id;
            $features[(int) $asset->id]['oem_manufacturer'] = $oem?->manufacturer;
            $features[(int) $asset->id]['oem_model'] = $oem?->model;
            $features[(int) $asset->id]['oem_service_interval_hours'] = $interval;
        }
    }

    /**
     * @param  list<int>  $assetIds
     * @param  array<int, array<string, mixed>>  $features
     */
    private function applyShiftWindows(int $companyId, array $assetIds, CarbonImmutable $asOf, array &$features): void
    {
        foreach (self::WINDOWS as $window) {
            $rows = DB::table('equipment_shift_logs')
                ->where('company_id', $companyId)
                ->whereIn('asset_id', $assetIds)
                ->whereBetween('logged_on', [$asOf->subDays($window)->toDateString(), $asOf->toDateString()])
                ->groupBy('asset_id')
                ->selectRaw(
                    'asset_id,
                     COUNT(*) as shifts,
                     COALESCE(SUM(worked_hours), 0) as worked,
                     COALESCE(SUM(scheduled_hours), 0) as scheduled,
                     COALESCE(SUM(preventive_hours), 0) as preventive,
                     COALESCE(SUM(corrective_hours), 0) as corrective,
                     COALESCE(SUM(operative_fail_hours), 0) as operative_fail,
                     COALESCE(SUM(standby_hours), 0) as standby,
                     COALESCE(SUM(oil_liters), 0) as oil,
                     COALESCE(SUM(diesel_liters), 0) as diesel,
                     COALESCE(SUM(coolant_liters), 0) as coolant,
                     AVG(availability) as availability,
                     AVG(utilization) as utilization'
                )
                ->get();

            foreach ($rows as $row) {
                $assetId = (int) $row->asset_id;
                $worked = (float) $row->worked;
                $features[$assetId] += [
                    "shifts_{$window}d" => (int) $row->shifts,
                    "worked_hours_{$window}d" => round($worked, 2),
                    "scheduled_hours_{$window}d" => round((float) $row->scheduled, 2),
                    "preventive_hours_{$window}d" => round((float) $row->preventive, 2),
                    "corrective_hours_{$window}d" => round((float) $row->corrective, 2),
                    "operative_fail_hours_{$window}d" => round((float) $row->operative_fail, 2),
                    "standby_hours_{$window}d" => round((float) $row->standby, 2),
                    "availability_{$window}d" => $row->availability === null ? null : round((float) $row->availability, 4),
                    "utilization_{$window}d" => $row->utilization === null ? null : round((float) $row->utilization, 4),
                    "oil_per_hour_{$window}d" => $worked > 0 ? round((float) $row->oil / $worked, 4) : null,
                    "diesel_per_hour_{$window}d" => $worked > 0 ? round((float) $row->diesel / $worked, 4) : null,
                    "coolant_per_hour_{$window}d" => $worked > 0 ? round((float) $row->coolant / $worked, 4) : null,
                ];
            }
        }
    }

    /**
     * @param  list<int>  $assetIds
     * @param  array<int, array<string, mixed>>  $features
     */
    private function applyLifetime(int $companyId, array $assetIds, CarbonImmutable $asOf, array &$features): void
    {
        $rows = DB::table('equipment_shift_logs')
            ->where('company_id', $companyId)
            ->whereIn('asset_id', $assetIds)
            ->where('logged_on', '<=', $asOf->toDateString())
            ->groupBy('asset_id')
            ->selectRaw(
                'asset_id,
                 COUNT(*) as shifts,
                 COALESCE(SUM(worked_hours), 0) as worked,
                 MIN(logged_on) as first_log,
                 MAX(logged_on) as last_log,
                 MAX(hour_meter_end) as hour_meter'
            )
            ->get();

        foreach ($rows as $row) {
            $assetId = (int) $row->asset_id;
            $firstLog = CarbonImmutable::parse((string) $row->first_log);
            $lastLog = CarbonImmutable::parse((string) $row->last_log);
            $features[$assetId] += [
                'shifts_total' => (int) $row->shifts,
                'worked_hours_total' => round((float) $row->worked, 2),
                'hour_meter' => $row->hour_meter === null ? null : round((float) $row->hour_meter, 2),
                'history_days' => $firstLog->diffInDays($lastLog) + 1,
                'days_since_last_log' => $lastLog->diffInDays($asOf),
                'first_log_on' => $firstLog->toDateString(),
                'last_log_on' => $lastLog->toDateString(),
            ];
        }

        // Última intervención preventiva registrada, para medir servicio vencido.
        $preventive = DB::table('equipment_shift_logs')
            ->where('company_id', $companyId)
            ->whereIn('asset_id', $assetIds)
            ->where('logged_on', '<=', $asOf->toDateString())
            ->where('preventive_hours', '>', 0)
            ->groupBy('asset_id')
            ->selectRaw('asset_id, MAX(logged_on) as last_preventive')
            ->get();

        foreach ($preventive as $row) {
            $assetId = (int) $row->asset_id;
            $last = CarbonImmutable::parse((string) $row->last_preventive);
            $features[$assetId] += [
                'last_preventive_on' => $last->toDateString(),
                'days_since_last_preventive' => $last->diffInDays($asOf),
            ];
        }
    }

    /**
     * @param  list<int>  $assetIds
     * @param  array<int, array<string, mixed>>  $features
     */
    private function applyFailures(int $companyId, array $assetIds, CarbonImmutable $asOf, array &$features): void
    {
        $realFailures = fn ($query) => $query->whereIn('maintenance_type', ['corrective', 'operational']);

        foreach ([30, 90] as $window) {
            $rows = DB::table('equipment_failures')
                ->where('company_id', $companyId)
                ->whereIn('asset_id', $assetIds)
                ->where('maintenance_type', 'corrective')
                ->whereBetween('started_at', [$asOf->subDays($window)->startOfDay(), $asOf->endOfDay()])
                ->groupBy('asset_id')
                ->selectRaw('asset_id, COUNT(*) as total, COALESCE(SUM(downtime_hours), 0) as downtime')
                ->get();

            foreach ($rows as $row) {
                $features[(int) $row->asset_id] += [
                    "failures_{$window}d" => (int) $row->total,
                    "failure_downtime_{$window}d" => round((float) $row->downtime, 2),
                ];
            }
        }

        $lifetime = DB::table('equipment_failures')
            ->where('company_id', $companyId)
            ->whereIn('asset_id', $assetIds)
            ->where('maintenance_type', 'corrective')
            ->where('started_at', '<=', $asOf->endOfDay())
            ->groupBy('asset_id')
            ->selectRaw(
                'asset_id,
                 COUNT(*) as total,
                 AVG(downtime_hours) as mttr,
                 MAX(started_at) as last_failure_at'
            )
            ->get();

        foreach ($lifetime as $row) {
            $assetId = (int) $row->asset_id;
            $lastFailure = CarbonImmutable::parse((string) $row->last_failure_at);
            $features[$assetId] += [
                'failures_total' => (int) $row->total,
                'mttr_hours' => $row->mttr === null ? null : round((float) $row->mttr, 2),
                'last_failure_on' => $lastFailure->toDateString(),
                'days_since_last_failure' => max(0, $lastFailure->startOfDay()->diffInDays($asOf)),
            ];
        }

        // Modos de falla históricos del activo: base para repartir el riesgo entre modos.
        $byMode = DB::table('equipment_failures as f')
            ->join('failure_modes as m', 'm.id', '=', 'f.failure_mode_id')
            ->where('f.company_id', $companyId)
            ->whereIn('f.asset_id', $assetIds)
            ->where('f.maintenance_type', 'corrective')
            ->where('f.started_at', '<=', $asOf->endOfDay())
            ->groupBy('f.asset_id', 'm.id', 'm.code')
            ->selectRaw('f.asset_id, m.id as mode_id, m.code, COUNT(*) as total')
            ->get();

        foreach ($byMode as $row) {
            $assetId = (int) $row->asset_id;
            $features[$assetId]['failure_modes_history'][(string) $row->code] = [
                'failure_mode_id' => (int) $row->mode_id,
                'count' => (int) $row->total,
            ];
        }

        unset($realFailures);
    }

    /**
     * @param  list<int>  $assetIds
     * @param  array<int, array<string, mixed>>  $features
     */
    private function applyEvents(int $companyId, array $assetIds, CarbonImmutable $asOf, array &$features): void
    {
        foreach ([7, 30] as $window) {
            $rows = DB::table('equipment_events')
                ->where('company_id', $companyId)
                ->whereIn('asset_id', $assetIds)
                ->whereBetween('occurred_at', [$asOf->subDays($window)->startOfDay(), $asOf->endOfDay()])
                ->groupBy('asset_id', 'severity')
                ->selectRaw('asset_id, severity, COUNT(*) as rows_count, COALESCE(SUM(occurrences), 0) as total')
                ->get();

            foreach ($rows as $row) {
                $assetId = (int) $row->asset_id;
                $key = match ((string) $row->severity) {
                    EquipmentEvent::SEVERITY_ALARM => "alarms_{$window}d",
                    EquipmentEvent::SEVERITY_WARNING => "warnings_{$window}d",
                    default => "messages_{$window}d",
                };
                $features[$assetId][$key] = (int) $row->total;
            }
        }

        // Códigos que más se repiten en la última semana: evidencia de precursor.
        $codes = DB::table('equipment_events')
            ->where('company_id', $companyId)
            ->whereIn('asset_id', $assetIds)
            ->whereIn('severity', [EquipmentEvent::SEVERITY_ALARM, EquipmentEvent::SEVERITY_WARNING])
            ->whereBetween('occurred_at', [$asOf->subDays(7)->startOfDay(), $asOf->endOfDay()])
            ->groupBy('asset_id', 'code', 'name', 'severity')
            ->selectRaw(
                'asset_id, code, name, severity,
                 COALESCE(SUM(occurrences), 0) as total,
                 COUNT(DISTINCT DATE(occurred_at)) as days'
            )
            ->orderByDesc('total')
            ->get();

        foreach ($codes as $row) {
            $assetId = (int) $row->asset_id;
            $existing = $features[$assetId]['event_codes_7d'] ?? [];
            if (count($existing) >= 8) {
                continue;
            }
            $existing[(string) $row->code] = [
                'name' => (string) $row->name,
                'severity' => (string) $row->severity,
                'occurrences' => (int) $row->total,
                'days' => (int) $row->days,
            ];
            $features[$assetId]['event_codes_7d'] = $existing;
        }
    }

    /**
     * @param  list<int>  $assetIds
     * @param  array<int, array<string, mixed>>  $features
     */
    private function applyWorkOrders(int $companyId, array $assetIds, CarbonImmutable $asOf, array &$features): void
    {
        $rows = DB::table('equipment_work_orders')
            ->where('company_id', $companyId)
            ->whereIn('asset_id', $assetIds)
            ->whereBetween('planned_for', [$asOf->subDays(90)->toDateString(), $asOf->toDateString()])
            ->groupBy('asset_id', 'status')
            ->selectRaw('asset_id, status, COUNT(*) as total, MIN(planned_for) as oldest')
            ->get();

        foreach ($rows as $row) {
            $assetId = (int) $row->asset_id;
            $status = (string) $row->status;
            $features[$assetId]["work_orders_{$status}_90d"] = (int) $row->total;
            if ($status !== 'executed') {
                $features[$assetId]['oldest_pending_work_order_on'] = (string) $row->oldest;
            }
        }

        foreach ($assetIds as $assetId) {
            $executed = (int) ($features[$assetId]['work_orders_executed_90d'] ?? 0);
            $skipped = (int) ($features[$assetId]['work_orders_skipped_90d'] ?? 0);
            $planned = (int) ($features[$assetId]['work_orders_planned_90d'] ?? 0);
            $total = $executed + $skipped + $planned;
            $features[$assetId]['pm_compliance_90d'] = $total > 0 ? round($executed / $total, 4) : null;
            $features[$assetId]['pm_backlog_90d'] = $skipped + $planned;
        }
    }

    /**
     * @param  list<int>  $assetIds
     * @param  array<int, array<string, mixed>>  $features
     */
    private function applyComponentLife(int $companyId, array $assetIds, CarbonImmutable $asOf, array &$features): void
    {
        $rows = DB::table('equipment_component_replacements')
            ->where('company_id', $companyId)
            ->whereIn('asset_id', $assetIds)
            ->where('replaced_at', '<=', $asOf->endOfDay())
            ->orderBy('replaced_at')
            ->get(['asset_id', 'component', 'replaced_at', 'hour_meter', 'expected_life_hours']);

        // Solo interesa el último reemplazo por componente.
        $latest = [];
        foreach ($rows as $row) {
            $latest[(int) $row->asset_id][(string) $row->component] = $row;
        }

        foreach ($latest as $assetId => $components) {
            $hourMeter = $features[$assetId]['hour_meter'] ?? null;
            $worst = null;
            $tracked = [];
            foreach ($components as $component => $row) {
                $used = null;
                if ($hourMeter !== null && $row->hour_meter !== null && $row->expected_life_hours > 0) {
                    $used = round(
                        max(0.0, (float) $hourMeter - (float) $row->hour_meter) / (float) $row->expected_life_hours,
                        4,
                    );
                    $worst = $worst === null ? $used : max($worst, $used);
                }
                $tracked[$component] = [
                    'replaced_on' => CarbonImmutable::parse((string) $row->replaced_at)->toDateString(),
                    'hours_since' => $hourMeter !== null && $row->hour_meter !== null
                        ? round((float) $hourMeter - (float) $row->hour_meter, 2)
                        : null,
                    'life_used_fraction' => $used,
                ];
            }
            $features[$assetId]['components'] = $tracked;
            $features[$assetId]['worst_component_life_used'] = $worst;
        }
    }

    /**
     * @param  list<int>  $assetIds
     * @param  array<int, array<string, mixed>>  $features
     */
    private function applyMeasurements(int $companyId, array $assetIds, CarbonImmutable $asOf, array &$features): void
    {
        $rows = DB::table('equipment_measurements')
            ->where('company_id', $companyId)
            ->whereIn('asset_id', $assetIds)
            ->where('metric', '!=', 'hour_meter')
            ->whereBetween('measured_at', [$asOf->subDays(90)->startOfDay(), $asOf->endOfDay()])
            ->orderBy('measured_at')
            ->get(['asset_id', 'metric', 'value', 'unit', 'measured_at', 'threshold_warning', 'threshold_critical']);

        $latest = [];
        foreach ($rows as $row) {
            $latest[(int) $row->asset_id][(string) $row->metric] = $row;
        }

        foreach ($latest as $assetId => $metrics) {
            $worst = 'normal';
            $summary = [];
            foreach ($metrics as $metric => $row) {
                $level = 'normal';
                if ($row->threshold_critical !== null && (float) $row->value >= (float) $row->threshold_critical) {
                    $level = 'critical';
                } elseif ($row->threshold_warning !== null && (float) $row->value >= (float) $row->threshold_warning) {
                    $level = 'warning';
                }
                if ($level === 'critical' || ($level === 'warning' && $worst === 'normal')) {
                    $worst = $level;
                }
                $summary[$metric] = [
                    'value' => round((float) $row->value, 4),
                    'unit' => $row->unit,
                    'level' => $level,
                    'measured_on' => CarbonImmutable::parse((string) $row->measured_at)->toDateString(),
                ];
            }
            $features[$assetId]['measurements'] = $summary;
            $features[$assetId]['worst_measurement_level'] = $worst;
        }
    }

    /**
     * Derivadas que solo dependen de lo ya agregado: tasas, tendencias y desviaciones.
     *
     * @param  array<int, array<string, mixed>>  $features
     */
    private function applyDerived(array &$features): void
    {
        foreach ($features as $assetId => $row) {
            // Si no hubo bitácora, la exposición sale de horas/conteo de servicios aplicados.
            foreach (self::WINDOWS as $window) {
                if (! isset($features[$assetId]["worked_hours_{$window}d"])
                    && isset($row["routine_hours_{$window}d"])) {
                    $features[$assetId]["worked_hours_{$window}d"] = $row["routine_hours_{$window}d"];
                }
                if (! isset($features[$assetId]["shifts_{$window}d"])
                    && isset($row["routines_{$window}d"])) {
                    $features[$assetId]["shifts_{$window}d"] = $row["routines_{$window}d"];
                }
            }
            if (! isset($features[$assetId]['worked_hours_total'])
                && isset($row['routine_hours_total'])) {
                $features[$assetId]['worked_hours_total'] = $row['routine_hours_total'];
            }
            if (! isset($features[$assetId]['shifts_total']) && isset($row['routines_total'])) {
                $features[$assetId]['shifts_total'] = (int) $row['routines_total'];
            }
            if (! isset($features[$assetId]['history_days'])
                && ! empty($row['first_routine_at'])
                && ! empty($row['last_routine_at'])) {
                $first = CarbonImmutable::parse((string) $row['first_routine_at']);
                $last = CarbonImmutable::parse((string) $row['last_routine_at']);
                $features[$assetId]['history_days'] = $first->diffInDays($last) + 1;
                $features[$assetId]['first_log_on'] = $first->toDateString();
                $features[$assetId]['last_log_on'] = $last->toDateString();
                $features[$assetId]['days_since_last_log'] = $row['days_since_last_routine'] ?? null;
            }

            $worked30 = (float) ($features[$assetId]['worked_hours_30d'] ?? $row['worked_hours_30d'] ?? 0);
            $shifts30 = (int) ($features[$assetId]['shifts_30d'] ?? $row['shifts_30d'] ?? 0);
            $routines30 = (int) ($row['routines_30d'] ?? 0);

            $daily = $shifts30 > 0 ? $worked30 / 30 : 0.0;
            if ($daily <= 0 && $routines30 > 0) {
                $avgMinutes = (float) ($row['avg_duration_minutes_30d'] ?? 60);
                $daily = ($routines30 * max(30.0, $avgMinutes) / 60) / 30;
            }
            $features[$assetId]['daily_operating_hours'] = round($daily, 3);

            // MTBF sobre horas de operación, que es la escala en la que envejece el equipo.
            $failuresTotal = (int) ($row['failures_total'] ?? 0);
            $workedTotal = (float) ($row['worked_hours_total'] ?? 0);
            $features[$assetId]['mtbf_hours'] = $failuresTotal > 0 && $workedTotal > 0
                ? round($workedTotal / $failuresTotal, 2)
                : null;

            $features[$assetId]['hours_since_last_failure'] = isset($row['days_since_last_failure'])
                ? round($daily * (int) $row['days_since_last_failure'], 2)
                : null;

            $features[$assetId]['hours_since_last_preventive'] = isset($row['days_since_last_preventive'])
                ? round($daily * (int) $row['days_since_last_preventive'], 2)
                : null;

            // Desviación de consumo: ventana corta contra la línea base de 90 días.
            foreach (['oil', 'diesel', 'coolant'] as $consumable) {
                $recent = $row["{$consumable}_per_hour_7d"] ?? null;
                $baseline = $row["{$consumable}_per_hour_90d"] ?? null;
                $features[$assetId]["{$consumable}_rate_ratio"] = $recent !== null && $baseline !== null && $baseline > 0
                    ? round($recent / $baseline, 4)
                    : null;
            }

            // Tendencia de disponibilidad: negativa significa que va empeorando.
            $availability7 = $row['availability_7d'] ?? null;
            $availability30 = $row['availability_30d'] ?? null;
            $features[$assetId]['availability_trend'] = $availability7 !== null && $availability30 !== null
                ? round($availability7 - $availability30, 4)
                : null;

            $scheduled30 = (float) ($row['scheduled_hours_30d'] ?? 0);
            $features[$assetId]['corrective_ratio_30d'] = $scheduled30 > 0
                ? round((float) ($row['corrective_hours_30d'] ?? 0) / $scheduled30, 4)
                : null;
        }
    }
}
