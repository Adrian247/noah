<?php

namespace App\Services\Predictive;

use App\Models\Asset;
use App\Models\CatalogItem;
use App\Models\EquipmentComponentReplacement;
use App\Models\EquipmentEvent;
use App\Models\EquipmentFailure;
use App\Models\EquipmentMeasurement;
use App\Models\EquipmentShiftLog;
use App\Models\EquipmentType;
use App\Models\EquipmentWorkOrder;
use App\Models\Site;
use App\Support\Predictive\FailureModeCatalog;
use App\Support\Predictive\OemCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Ingesta de un dataset de bitácora normalizado (contrato `phoenix.predictive.ingest/v1`).
 *
 * El ETL vive en `ml/phoenix-predict/scripts/extract_logbooks.py`; aquí solo se resuelve el
 * dominio (sitio, tipo de equipo, catálogo, activo) y se hace un upsert idempotente.
 */
class LogbookIngestService
{
    public const CONTRACT = 'phoenix.predictive.ingest/v1';

    private const CHUNK = 500;

    public function __construct(private readonly FailureTextNormalizer $normalizer) {}

    /**
     * @param  array<string, mixed>  $dataset
     * @return array<string, int>
     */
    public function ingest(int $companyId, array $dataset): array
    {
        $contract = (string) data_get($dataset, 'meta.contract');
        if ($contract !== self::CONTRACT) {
            throw new InvalidArgumentException(
                "Contrato de dataset no soportado: '{$contract}'. Se espera '".self::CONTRACT."'."
            );
        }

        FailureModeCatalog::syncForCompany($companyId);
        $this->normalizer->forget();

        $site = $this->resolveSite($companyId, (string) ($dataset['site'] ?? 'Sitio principal'));
        $assets = $this->syncAssets($companyId, $site, (array) ($dataset['assets'] ?? []));

        return [
            'assets' => count($assets),
            'shift_logs' => $this->syncShiftLogs($companyId, $assets, (array) ($dataset['shift_logs'] ?? [])),
            'events' => $this->syncEvents($companyId, $assets, (array) ($dataset['events'] ?? [])),
            'failures' => $this->syncFailures($companyId, $assets, (array) ($dataset['failures'] ?? [])),
            'work_orders' => $this->syncWorkOrders($companyId, $assets, (array) ($dataset['work_orders'] ?? [])),
            'component_replacements' => $this->syncReplacements(
                $companyId,
                $assets,
                (array) ($dataset['component_replacements'] ?? []),
            ),
            'measurements' => $this->syncMeasurements($companyId, $assets, (array) ($dataset['measurements'] ?? [])),
        ];
    }

    private function resolveSite(int $companyId, string $name): Site
    {
        return Site::withoutGlobalScope('company')->firstOrCreate(
            ['company_id' => $companyId, 'name' => $name],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int> Mapa de tag a asset id.
     */
    private function syncAssets(int $companyId, Site $site, array $rows): array
    {
        $map = [];
        $types = [];
        $catalog = [];

        foreach ($rows as $row) {
            $tag = trim((string) ($row['tag'] ?? ''));
            if ($tag === '') {
                continue;
            }

            $class = strtoupper((string) ($row['equipment_class'] ?? 'OTRO'));
            $types[$class] ??= $this->resolveEquipmentType($companyId, $class);

            $manufacturer = $row['manufacturer'] ?? null;
            $model = $row['model'] ?? null;
            $catalogCode = $this->catalogCode($class, $manufacturer, $model);
            $catalog[$catalogCode] ??= $this->resolveCatalogItem(
                $companyId,
                $types[$class],
                $catalogCode,
                $class,
                $manufacturer,
                $model,
            );

            $asset = Asset::withoutGlobalScope('company')->updateOrCreate(
                ['company_id' => $companyId, 'tag' => $tag],
                [
                    'site_id' => $site->id,
                    'catalog_item_id' => $catalog[$catalogCode],
                    'serial_number' => $row['serial_number'] ?? null,
                    'location_label' => $row['location_code'] ?? ($row['area'] ?? null),
                    'status' => 'active',
                    'metadata' => array_filter(
                        [
                            'name' => $row['name'] ?? null,
                            'equipment_class' => $class,
                            'application' => $row['application'] ?? null,
                            'area' => $row['area'] ?? null,
                            'location_code' => $row['location_code'] ?? null,
                            'manufacturer' => $manufacturer,
                            'model' => $model,
                            'source_class' => $row['source_class'] ?? null,
                            'capacity' => $row['capacity'] ?? null,
                            'hour_meter' => $row['hour_meter'] ?? null,
                            'is_main' => $row['is_main'] ?? null,
                        ],
                        static fn ($value) => $value !== null,
                    ),
                ],
            );

            $map[$tag] = (int) $asset->id;
        }

        return $map;
    }

    private function resolveEquipmentType(int $companyId, string $class): int
    {
        $type = EquipmentType::withoutGlobalScope('company')->firstOrCreate(
            ['company_id' => $companyId, 'code' => Str::limit($class, 60, '')],
            ['name' => Str::title(str_replace('_', ' ', strtolower($class)))],
        );

        return (int) $type->id;
    }

    private function catalogCode(string $class, ?string $manufacturer, ?string $model): string
    {
        $parts = array_filter([$class, $manufacturer, $model]);

        return Str::limit(Str::upper(Str::slug(implode(' ', $parts), '-')), 60, '');
    }

    private function resolveCatalogItem(
        int $companyId,
        int $equipmentTypeId,
        string $code,
        string $class,
        ?string $manufacturer,
        ?string $model,
    ): int {
        $name = trim(implode(' ', array_filter([
            Str::title(str_replace('_', ' ', strtolower($class))),
            $manufacturer,
            $model,
        ])));

        $oemId = OemCatalog::resolveOemModelId(
            is_string($manufacturer) ? $manufacturer : null,
            is_string($model) ? $model : null,
        );

        $item = CatalogItem::withoutGlobalScope('company')->updateOrCreate(
            ['company_id' => $companyId, 'code' => $code],
            [
                'equipment_type_id' => $equipmentTypeId,
                'name' => $name !== '' ? $name : $class,
                'manufacturer' => $manufacturer,
                'oem_equipment_model_id' => $oemId,
                'specifications' => array_filter([
                    'modelo' => $model,
                    'equipment_class' => $class,
                ]),
            ],
        );

        return (int) $item->id;
    }

    /**
     * @param  array<string, int>  $assets
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncShiftLogs(int $companyId, array $assets, array $rows): int
    {
        $payload = [];
        foreach ($rows as $row) {
            $assetId = $assets[$row['asset_tag'] ?? ''] ?? null;
            if ($assetId === null || empty($row['logged_on'])) {
                continue;
            }

            $payload[] = [
                'company_id' => $companyId,
                'asset_id' => $assetId,
                'logged_on' => $row['logged_on'],
                'shift' => (string) ($row['shift'] ?? 'FULL'),
                'scheduled_hours' => (float) ($row['scheduled_hours'] ?? 0),
                'worked_hours' => (float) ($row['worked_hours'] ?? 0),
                'standby_hours' => (float) ($row['standby_hours'] ?? 0),
                'preventive_hours' => (float) ($row['preventive_hours'] ?? 0),
                'corrective_hours' => (float) ($row['corrective_hours'] ?? 0),
                'operative_fail_hours' => (float) ($row['operative_fail_hours'] ?? 0),
                'no_operator_hours' => (float) ($row['no_operator_hours'] ?? 0),
                'availability' => $row['availability'] ?? null,
                'utilization' => $row['utilization'] ?? null,
                'hour_meter_start' => $row['hour_meter_start'] ?? null,
                'hour_meter_end' => $row['hour_meter_end'] ?? null,
                'diesel_liters' => $row['diesel_liters'] ?? null,
                'oil_liters' => $row['oil_liters'] ?? null,
                'coolant_liters' => $row['coolant_liters'] ?? null,
                'production' => isset($row['production']) ? json_encode($row['production']) : null,
                'location_label' => $row['location_label'] ?? null,
                'equipment_status' => $row['equipment_status'] ?? null,
                'failure_text' => $row['failure_text'] ?? null,
                'comments' => $row['comments'] ?? null,
                'source' => (string) ($row['source'] ?? 'import'),
                'external_ref' => $row['external_ref'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $this->upsert(
            (new EquipmentShiftLog)->getTable(),
            $payload,
            ['asset_id', 'logged_on', 'shift'],
        );
    }

    /**
     * @param  array<string, int>  $assets
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncEvents(int $companyId, array $assets, array $rows): int
    {
        $payload = [];
        foreach ($rows as $row) {
            $assetId = $assets[$row['asset_tag'] ?? ''] ?? null;
            $code = trim((string) ($row['code'] ?? ''));
            if ($assetId === null || $code === '' || empty($row['occurred_at'])) {
                continue;
            }

            $payload[] = [
                'company_id' => $companyId,
                'asset_id' => $assetId,
                'occurred_at' => $row['occurred_at'],
                'code' => $code,
                'name' => (string) ($row['name'] ?? $code),
                'severity' => $row['severity'] ?? EquipmentEvent::severityFromCode($code),
                'occurrences' => max(1, (int) ($row['occurrences'] ?? 1)),
                'source' => (string) ($row['source'] ?? 'plc'),
                'payload' => isset($row['payload']) ? json_encode($row['payload']) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $this->upsert(
            (new EquipmentEvent)->getTable(),
            $payload,
            ['asset_id', 'occurred_at', 'code', 'source'],
        );
    }

    /**
     * @param  array<string, int>  $assets
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncFailures(int $companyId, array $assets, array $rows): int
    {
        // El modo de falla se resuelve desde el texto libre con la taxonomía de la empresa.
        $classByAsset = Asset::withoutGlobalScope('company')
            ->whereIn('id', array_values($assets))
            ->get(['id', 'tag', 'metadata'])
            ->mapWithKeys(fn (Asset $asset) => [$asset->id => $asset->equipmentClass()])
            ->all();

        $payload = [];
        foreach ($rows as $row) {
            $assetId = $assets[$row['asset_tag'] ?? ''] ?? null;
            if ($assetId === null || empty($row['started_at'])) {
                continue;
            }

            $mode = $this->normalizer->resolveOrFallback(
                $companyId,
                $row['reported_text'] ?? null,
                $classByAsset[$assetId] ?? null,
            );

            $payload[] = [
                'company_id' => $companyId,
                'asset_id' => $assetId,
                'failure_mode_id' => $mode?->id,
                'started_at' => $row['started_at'],
                'ended_at' => $row['ended_at'] ?? null,
                'downtime_hours' => $row['downtime_hours'] ?? null,
                'maintenance_type' => (string) ($row['maintenance_type'] ?? 'corrective'),
                'reported_text' => $row['reported_text'] ?? null,
                'hour_meter' => $row['hour_meter'] ?? null,
                'cost' => $row['cost'] ?? null,
                'source' => (string) ($row['source'] ?? 'import'),
                'external_ref' => $row['external_ref'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $this->upsert(
            (new EquipmentFailure)->getTable(),
            $payload,
            ['asset_id', 'started_at', 'maintenance_type'],
        );
    }

    /**
     * @param  array<string, int>  $assets
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncWorkOrders(int $companyId, array $assets, array $rows): int
    {
        $payload = [];
        foreach ($rows as $row) {
            $orderNumber = trim((string) ($row['order_number'] ?? ''));
            if ($orderNumber === '') {
                continue;
            }

            $payload[] = [
                'company_id' => $companyId,
                'asset_id' => $assets[$row['asset_tag'] ?? ''] ?? null,
                'order_number' => $orderNumber,
                'description' => $row['description'] ?? null,
                'work_center' => $row['work_center'] ?? null,
                'location_code' => $row['location_code'] ?? null,
                'planned_for' => $row['planned_for'] ?? null,
                'executed_on' => $row['executed_on'] ?? null,
                'status' => (string) ($row['status'] ?? EquipmentWorkOrder::STATUS_PLANNED),
                'skip_reason' => $row['skip_reason'] ?? null,
                'supervisor' => $row['supervisor'] ?? null,
                'source' => (string) ($row['source'] ?? 'import'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $this->upsert(
            (new EquipmentWorkOrder)->getTable(),
            $payload,
            ['company_id', 'order_number'],
        );
    }

    /**
     * @param  array<string, int>  $assets
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncReplacements(int $companyId, array $assets, array $rows): int
    {
        $payload = [];
        foreach ($rows as $row) {
            $assetId = $assets[$row['asset_tag'] ?? ''] ?? null;
            $component = trim((string) ($row['component'] ?? ''));
            if ($assetId === null || $component === '' || empty($row['replaced_at'])) {
                continue;
            }

            $payload[] = [
                'company_id' => $companyId,
                'asset_id' => $assetId,
                'component' => $component,
                'description' => $row['description'] ?? null,
                'replaced_at' => $row['replaced_at'],
                'hour_meter' => $row['hour_meter'] ?? null,
                'expected_life_hours' => $row['expected_life_hours'] ?? null,
                'source' => (string) ($row['source'] ?? 'import'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $this->upsert(
            (new EquipmentComponentReplacement)->getTable(),
            $payload,
            ['asset_id', 'component', 'replaced_at'],
        );
    }

    /**
     * @param  array<string, int>  $assets
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncMeasurements(int $companyId, array $assets, array $rows): int
    {
        $payload = [];
        foreach ($rows as $row) {
            $assetId = $assets[$row['asset_tag'] ?? ''] ?? null;
            $metric = trim((string) ($row['metric'] ?? ''));
            if ($assetId === null || $metric === '' || ! isset($row['value'], $row['measured_at'])) {
                continue;
            }

            $payload[] = [
                'company_id' => $companyId,
                'asset_id' => $assetId,
                'metric' => $metric,
                'value' => (float) $row['value'],
                'unit' => $row['unit'] ?? null,
                'measured_at' => $row['measured_at'],
                'threshold_warning' => $row['threshold_warning'] ?? null,
                'threshold_critical' => $row['threshold_critical'] ?? null,
                'source' => (string) ($row['source'] ?? 'import'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $this->upsert(
            (new EquipmentMeasurement)->getTable(),
            $payload,
            ['asset_id', 'metric', 'measured_at'],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $uniqueBy
     */
    private function upsert(string $table, array $rows, array $uniqueBy): int
    {
        if ($rows === []) {
            return 0;
        }

        $columns = array_keys($rows[0]);
        $update = array_values(array_diff($columns, [...$uniqueBy, 'created_at']));

        $total = 0;
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table($table)->upsert($chunk, $uniqueBy, $update);
            $total += count($chunk);
        }

        return $total;
    }

    /**
     * Derivación de horómetro a serie de mediciones, útil para tendencias y RUL.
     *
     * Se toma la lectura más alta del día por activo: con varios turnos por día, el cierre del
     * último turno es la lectura acumulada válida.
     */
    public function backfillHourMeterMeasurements(int $companyId): int
    {
        $rows = EquipmentShiftLog::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->whereNotNull('hour_meter_end')
            ->groupBy('asset_id', 'logged_on')
            ->orderBy('logged_on')
            ->selectRaw('asset_id, logged_on, MAX(hour_meter_end) as hour_meter_end')
            ->get();

        $payload = $rows->map(fn ($log) => [
            'company_id' => $companyId,
            'asset_id' => $log->asset_id,
            'metric' => 'hour_meter',
            'value' => (float) $log->hour_meter_end,
            'unit' => 'h',
            'measured_at' => CarbonImmutable::parse($log->logged_on)->endOfDay(),
            'threshold_warning' => null,
            'threshold_critical' => null,
            'source' => 'derived',
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        return $this->upsert(
            (new EquipmentMeasurement)->getTable(),
            $payload,
            ['asset_id', 'metric', 'measured_at'],
        );
    }
}
