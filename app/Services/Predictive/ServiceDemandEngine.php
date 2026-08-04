<?php

namespace App\Services\Predictive;

use App\Enums\PredictiveAlgorithmKind;
use App\Enums\RoutineStatus;
use App\Enums\ServiceCategory;
use App\Models\CatalogItem;
use App\Models\Client;
use App\Models\PredictiveTrainingDocument;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Motor compartido de demanda (Poisson + tendencia + recencia) para manufactura e inventario.
 * Acepta calibración aprendida en entrenamiento de plataforma.
 */
class ServiceDemandEngine
{
    public const MODEL_VERSION = 'demand-v1';

    /**
     * @param  array{
     *     client_id?: int|null,
     *     catalog_item_id?: int|null,
     *     horizon_days?: int|null,
     *     limit?: int|null,
     *     as_of?: string|null,
     *     calibration?: array<string, mixed>|null
     * }  $filters
     * @return array<string, mixed>
     */
    public function predictManufacturing(int $companyId, array $filters = []): array
    {
        $horizonDays = max(7, min(90, (int) ($filters['horizon_days'] ?? 30)));
        $limit = max(1, min(100, (int) ($filters['limit'] ?? 20)));
        $asOf = isset($filters['as_of']) && $filters['as_of']
            ? CarbonImmutable::parse($filters['as_of'])
            : CarbonImmutable::today();
        $lookbackDays = max(120, $horizonDays * 4);
        $from = $asOf->subDays($lookbackDays)->startOfDay();
        $calibration = is_array($filters['calibration'] ?? null) ? $filters['calibration'] : [];

        $validated = [
            RoutineStatus::Validated->value,
            RoutineStatus::PendingBilling->value,
            RoutineStatus::Invoiced->value,
        ];

        $query = DB::table('routines as r')
            ->join('routine_types as t', 't.id', '=', 'r.routine_type_id')
            ->leftJoin('routine_executions as e', function ($join) {
                $join->on('e.routine_id', '=', 'r.id')
                    ->whereRaw('e.id = (select max(id) from routine_executions where routine_id = r.id)');
            })
            ->where('r.company_id', $companyId)
            ->where('t.service_category', ServiceCategory::Manufacturing->value)
            ->whereNotNull('r.client_id')
            ->whereIn('r.status', $validated)
            ->where(function ($q) use ($from, $asOf) {
                $q->whereBetween('e.validated_at', [$from->toDateTimeString(), $asOf->endOfDay()->toDateTimeString()])
                    ->orWhere(function ($inner) use ($from, $asOf) {
                        $inner->whereNull('e.validated_at')
                            ->whereBetween('r.scheduled_at', [$from->toDateTimeString(), $asOf->endOfDay()->toDateTimeString()]);
                    });
            });

        if (! empty($filters['client_id'])) {
            $query->where('r.client_id', (int) $filters['client_id']);
        }

        $rows = $query
            ->groupBy('r.client_id', 'r.routine_type_id', 't.name')
            ->selectRaw(
                'r.client_id,
                 r.routine_type_id,
                 t.name as service_type_name,
                 COUNT(*) as events,
                 MAX(COALESCE(e.validated_at, r.scheduled_at)) as last_at,
                 MIN(COALESCE(e.validated_at, r.scheduled_at)) as first_at'
            )
            ->get();

        $docBoosts = $this->documentFrequencyBoosts(
            PredictiveAlgorithmKind::Manufacturing,
            $calibration,
        );

        $clientIds = $rows->pluck('client_id')->map(fn ($id) => (int) $id)->unique()->all();
        $clients = Client::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->whereIn('id', $clientIds)
            ->get(['id', 'trade_name', 'legal_name', 'code'])
            ->keyBy('id');

        $predictions = [];
        foreach ($rows as $row) {
            $client = $clients->get((int) $row->client_id);
            $key = strtolower(trim(($client?->code ?? '').'|'.(string) $row->service_type_name));
            $scored = $this->scoreSeries(
                events: (int) $row->events,
                firstAt: $row->first_at ? CarbonImmutable::parse((string) $row->first_at) : null,
                lastAt: $row->last_at ? CarbonImmutable::parse((string) $row->last_at) : null,
                asOf: $asOf,
                lookbackDays: $lookbackDays,
                horizonDays: $horizonDays,
                docBoost: (float) ($docBoosts[$key] ?? 1.0),
                calibration: $calibration,
            );

            $predictions[] = array_merge($scored, [
                'client_id' => (int) $row->client_id,
                'client_name' => $client?->trade_name ?? $client?->legal_name ?? $client?->code ?? 'Cliente #'.$row->client_id,
                'client_code' => $client?->code,
                'routine_type_id' => (int) $row->routine_type_id,
                'routine_type_name' => (string) $row->service_type_name,
                'service_type_name' => (string) $row->service_type_name,
                'service_category' => ServiceCategory::Manufacturing->value,
                'service_line' => ServiceCategory::Manufacturing->value,
                'service_line_label' => ServiceCategory::Manufacturing->label(),
                'model_kind' => PredictiveAlgorithmKind::Manufacturing->value,
                'model_version' => self::MODEL_VERSION,
            ]);
        }

        usort($predictions, fn (array $a, array $b) => $b['score'] <=> $a['score']);
        $ranked = array_slice($predictions, 0, $limit);

        return [
            'as_of' => $asOf->toDateString(),
            'horizon_days' => $horizonDays,
            'lookback_days' => $lookbackDays,
            'kind' => PredictiveAlgorithmKind::Manufacturing->value,
            'evaluated' => count($predictions),
            'returned' => count($ranked),
            'predictions' => $ranked,
            'notes' => $predictions === []
                ? ['No hay servicios de manufactura validados con cliente en el periodo.']
                : [],
        ];
    }

    /**
     * Demanda de artículos: consumos en servicios + documentos de solicitudes históricas.
     *
     * @param  array{
     *     client_id?: int|null,
     *     catalog_item_id?: int|null,
     *     horizon_days?: int|null,
     *     limit?: int|null,
     *     as_of?: string|null,
     *     calibration?: array<string, mixed>|null
     * }  $filters
     * @return array<string, mixed>
     */
    public function predictInventory(int $companyId, array $filters = []): array
    {
        $horizonDays = max(7, min(90, (int) ($filters['horizon_days'] ?? 30)));
        $limit = max(1, min(100, (int) ($filters['limit'] ?? 20)));
        $asOf = isset($filters['as_of']) && $filters['as_of']
            ? CarbonImmutable::parse($filters['as_of'])
            : CarbonImmutable::today();
        $lookbackDays = max(120, $horizonDays * 4);
        $from = $asOf->subDays($lookbackDays)->startOfDay();
        $calibration = is_array($filters['calibration'] ?? null) ? $filters['calibration'] : [];

        $validated = [
            RoutineStatus::Validated->value,
            RoutineStatus::PendingBilling->value,
            RoutineStatus::Invoiced->value,
        ];

        $query = DB::table('routine_consumptions as c')
            ->join('routine_executions as e', 'e.id', '=', 'c.routine_execution_id')
            ->join('routines as r', 'r.id', '=', 'e.routine_id')
            ->join('supply_items as s', 's.id', '=', 'c.supply_item_id')
            ->where('r.company_id', $companyId)
            ->whereNotNull('r.client_id')
            ->whereIn('r.status', $validated)
            ->whereBetween('e.validated_at', [$from->toDateTimeString(), $asOf->endOfDay()->toDateTimeString()]);

        if (! empty($filters['client_id'])) {
            $query->where('r.client_id', (int) $filters['client_id']);
        }

        $rows = $query
            ->groupBy('r.client_id', 's.sku', 's.name')
            ->selectRaw(
                'r.client_id,
                 s.sku as catalog_item_code,
                 s.name as item_name,
                 COUNT(*) as events,
                 COALESCE(SUM(c.quantity), 0) as quantity_total,
                 MAX(e.validated_at) as last_at,
                 MIN(e.validated_at) as first_at'
            )
            ->get();

        // Enlazar también por código de catálogo cuando el SKU coincide con catalog_items.code
        $docBoosts = $this->documentFrequencyBoosts(
            PredictiveAlgorithmKind::Inventory,
            $calibration,
        );

        $clientIds = $rows->pluck('client_id')->map(fn ($id) => (int) $id)->unique()->all();
        $clients = Client::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->whereIn('id', $clientIds)
            ->get(['id', 'trade_name', 'legal_name', 'code'])
            ->keyBy('id');

        $catalogByCode = CatalogItem::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('is_system_template', false)
            ->get(['id', 'code', 'name'])
            ->keyBy(fn ($i) => strtolower((string) $i->code));

        $predictions = [];
        foreach ($rows as $row) {
            $client = $clients->get((int) $row->client_id);
            $code = (string) $row->catalog_item_code;
            $key = strtolower(trim(($client?->code ?? '').'|'.$code));
            $scored = $this->scoreSeries(
                events: max(1, (int) $row->events),
                firstAt: $row->first_at ? CarbonImmutable::parse((string) $row->first_at) : null,
                lastAt: $row->last_at ? CarbonImmutable::parse((string) $row->last_at) : null,
                asOf: $asOf,
                lookbackDays: $lookbackDays,
                horizonDays: $horizonDays,
                docBoost: (float) ($docBoosts[$key] ?? 1.0),
                calibration: $calibration,
            );

            $catalog = $catalogByCode->get(strtolower($code));
            $predictions[] = array_merge($scored, [
                'client_id' => (int) $row->client_id,
                'client_name' => $client?->trade_name ?? $client?->legal_name ?? $client?->code ?? 'Cliente #'.$row->client_id,
                'client_code' => $client?->code,
                'catalog_item_id' => $catalog?->id,
                'catalog_item_code' => $code,
                'item_name' => $catalog?->name ?? (string) $row->item_name,
                'quantity_in_lookback' => (float) $row->quantity_total,
                'model_kind' => PredictiveAlgorithmKind::Inventory->value,
                'model_version' => self::MODEL_VERSION,
            ]);
        }

        // Incorporar claves solo presentes en documentos de entrenamiento (demanda declarada).
        foreach ($docBoosts as $key => $boost) {
            if ($boost <= 1.0) {
                continue;
            }
            [$clientCode, $itemCode] = array_pad(explode('|', $key, 2), 2, '');
            $already = collect($predictions)->contains(
                fn (array $p) => strtolower((string) ($p['client_code'] ?? '')).'|'.strtolower((string) ($p['catalog_item_code'] ?? '')) === $key
            );
            if ($already || $clientCode === '' || $itemCode === '') {
                continue;
            }
            $client = Client::withoutGlobalScope('company')
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(code) = ?', [$clientCode])
                ->first();
            if ($client === null) {
                continue;
            }
            $catalog = $catalogByCode->get($itemCode);
            $scored = $this->scoreSeries(
                events: max(1, (int) round($boost)),
                firstAt: $asOf->subDays((int) ($lookbackDays / 2)),
                lastAt: $asOf->subDays(7),
                asOf: $asOf,
                lookbackDays: $lookbackDays,
                horizonDays: $horizonDays,
                docBoost: $boost,
                calibration: $calibration,
            );
            $predictions[] = array_merge($scored, [
                'client_id' => $client->id,
                'client_name' => $client->trade_name ?? $client->legal_name ?? $client->code,
                'client_code' => $client->code,
                'catalog_item_id' => $catalog?->id,
                'catalog_item_code' => $itemCode,
                'item_name' => $catalog?->name ?? $itemCode,
                'quantity_in_lookback' => 0,
                'model_kind' => PredictiveAlgorithmKind::Inventory->value,
                'model_version' => self::MODEL_VERSION,
                'from_training_document' => true,
            ]);
        }

        usort($predictions, fn (array $a, array $b) => $b['score'] <=> $a['score']);
        $ranked = array_slice($predictions, 0, $limit);

        return [
            'as_of' => $asOf->toDateString(),
            'horizon_days' => $horizonDays,
            'lookback_days' => $lookbackDays,
            'kind' => PredictiveAlgorithmKind::Inventory->value,
            'evaluated' => count($predictions),
            'returned' => count($ranked),
            'predictions' => $ranked,
            'notes' => $predictions === []
                ? ['No hay consumos de inventario ligados a clientes ni documentos de demanda en el periodo.']
                : [],
        ];
    }

    /**
     * Regresión temporal: ¿hubo un evento en el horizonte tras el corte?
     *
     * @return array<string, mixed>
     */
    public function backtestManufacturing(int $companyId, int $horizonDays = 30, int $strideDays = 14): array
    {
        return $this->backtestDemand(
            companyId: $companyId,
            kind: PredictiveAlgorithmKind::Manufacturing,
            horizonDays: $horizonDays,
            strideDays: $strideDays,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function backtestInventory(int $companyId, int $horizonDays = 30, int $strideDays = 14): array
    {
        return $this->backtestDemand(
            companyId: $companyId,
            kind: PredictiveAlgorithmKind::Inventory,
            horizonDays: $horizonDays,
            strideDays: $strideDays,
        );
    }

    /**
     * @param  array<string, mixed>  $calibration
     * @return array<string, mixed>
     */
    private function scoreSeries(
        int $events,
        ?CarbonImmutable $firstAt,
        ?CarbonImmutable $lastAt,
        CarbonImmutable $asOf,
        int $lookbackDays,
        int $horizonDays,
        float $docBoost,
        array $calibration,
    ): array {
        $spanDays = max(1, $firstAt && $lastAt ? max(1, $firstAt->diffInDays($lastAt)) : $lookbackDays);
        $ratePerDay = $events / max(1, min($lookbackDays, $spanDays));
        $daysSince = $lastAt ? $lastAt->diffInDays($asOf) : $lookbackDays;

        // Tendencia: si la mitad reciente concentra más eventos, subir score.
        $trend = 1.0;
        if ($lastAt && $firstAt && $spanDays >= 28) {
            $mid = $firstAt->addDays((int) ($spanDays / 2));
            // Aproximación: más peso a recencia ya captura tendencia; boost suave.
            $trend = 1 + max(0, (21 - min(21, $daysSince)) / 42);
        }

        $recencyBoost = 1 + max(0, (60 - $daysSince) / 60);
        $globalBoost = (float) ($calibration['global_rate_multiplier'] ?? 1.0);
        $expected = round($ratePerDay * $horizonDays * $docBoost * $globalBoost * $trend, 4);
        $score = round($expected * $recencyBoost, 4);
        $probability = round(min(0.95, max(0.05, 1 - exp(-max(0.01, $score)))), 4);

        return [
            'events_in_lookback' => $events,
            'last_at' => $lastAt?->toDateString(),
            'days_since_last' => $daysSince,
            'expected_requests' => $expected,
            'probability' => $probability,
            'score' => $score,
            'drivers' => [
                [
                    'code' => 'frecuencia',
                    'label' => 'Frecuencia histórica',
                    'evidence' => sprintf('%d eventos · tasa %.3f / día.', $events, $ratePerDay),
                ],
                [
                    'code' => 'recencia',
                    'label' => 'Recencia',
                    'evidence' => $lastAt
                        ? sprintf('Último evento %s (hace %d días).', $lastAt->toDateString(), $daysSince)
                        : 'Sin fecha de último evento.',
                ],
                [
                    'code' => 'calibracion',
                    'label' => 'Calibración / documentos',
                    'evidence' => sprintf('Multiplicador documento=%.2f · global=%.2f · tendencia=%.2f.', $docBoost, $globalBoost, $trend),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $calibration
     * @return array<string, float> key => boost
     */
    private function documentFrequencyBoosts(PredictiveAlgorithmKind $kind, array $calibration): array
    {
        $fromCal = $calibration['pair_boosts'] ?? [];
        if (is_array($fromCal) && $fromCal !== []) {
            $out = [];
            foreach ($fromCal as $k => $v) {
                $out[strtolower((string) $k)] = max(1.0, (float) $v);
            }

            return $out;
        }

        // Fallback: leer documentos ready del kind.
        $boosts = [];
        $docs = PredictiveTrainingDocument::query()
            ->where('kind', $kind->value)
            ->where('status', PredictiveTrainingDocument::STATUS_READY)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        foreach ($docs as $doc) {
            if (! Storage::disk($doc->disk)->exists($doc->path)) {
                continue;
            }
            $raw = Storage::disk($doc->disk)->get($doc->path);
            try {
                $parsed = app(PredictiveTrainingDocumentParser::class)->parse($raw, $doc->original_filename, $kind->value);
            } catch (\Throwable) {
                continue;
            }
            foreach ($parsed['records'] as $record) {
                if ($kind === PredictiveAlgorithmKind::Manufacturing) {
                    $key = strtolower(trim(($record['client_code'] ?? '').'|'.($record['service_type'] ?? '')));
                } else {
                    $key = strtolower(trim(($record['client_code'] ?? '').'|'.($record['catalog_item_code'] ?? '')));
                }
                if ($key === '|' || $key === '') {
                    continue;
                }
                $qty = max(1, (int) ($record['quantity'] ?? 1));
                $boosts[$key] = ($boosts[$key] ?? 1.0) + log(1 + $qty);
            }
        }

        return $boosts;
    }

    /**
     * @return array<string, mixed>
     */
    private function backtestDemand(
        int $companyId,
        PredictiveAlgorithmKind $kind,
        int $horizonDays,
        int $strideDays,
    ): array {
        $asOfEnd = CarbonImmutable::today()->subDays($horizonDays);
        $asOfStart = $asOfEnd->subDays(180);
        $scored = [];

        for ($cursor = $asOfStart; $cursor->lte($asOfEnd); $cursor = $cursor->addDays($strideDays)) {
            $result = $kind === PredictiveAlgorithmKind::Manufacturing
                ? $this->predictManufacturing($companyId, [
                    'as_of' => $cursor->toDateString(),
                    'horizon_days' => $horizonDays,
                    'limit' => 50,
                ])
                : $this->predictInventory($companyId, [
                    'as_of' => $cursor->toDateString(),
                    'horizon_days' => $horizonDays,
                    'limit' => 50,
                ]);

            foreach ($result['predictions'] as $prediction) {
                $label = $this->labelOccurred(
                    $companyId,
                    $kind,
                    $prediction,
                    $cursor,
                    $horizonDays,
                );
                $scored[] = [
                    'label' => $label,
                    'probability' => (float) $prediction['probability'],
                ];
            }
        }

        if ($scored === []) {
            return [
                'kind' => $kind->value,
                'rows' => 0,
                'notes' => ['Sin suficientes cortes temporales para regresión.'],
            ];
        }

        $positives = count(array_filter($scored, fn (array $r) => $r['label'] === 1));

        return [
            'kind' => $kind->value,
            'horizon_days' => $horizonDays,
            'stride_days' => $strideDays,
            'rows' => count($scored),
            'positives' => $positives,
            'base_rate' => round($positives / max(1, count($scored)), 4),
            'roc_auc' => $this->rocAuc($scored),
            'alert_metrics' => $this->alertMetrics($scored, 0.45),
        ];
    }

    /**
     * @param  array<string, mixed>  $prediction
     */
    private function labelOccurred(
        int $companyId,
        PredictiveAlgorithmKind $kind,
        array $prediction,
        CarbonImmutable $asOf,
        int $horizonDays,
    ): int {
        $from = $asOf->addDay()->startOfDay();
        $to = $asOf->addDays($horizonDays)->endOfDay();
        $clientId = (int) ($prediction['client_id'] ?? 0);
        if ($clientId <= 0) {
            return 0;
        }

        if ($kind === PredictiveAlgorithmKind::Manufacturing) {
            $typeId = (int) ($prediction['routine_type_id'] ?? 0);
            $exists = DB::table('routines as r')
                ->leftJoin('routine_executions as e', 'e.routine_id', '=', 'r.id')
                ->where('r.company_id', $companyId)
                ->where('r.client_id', $clientId)
                ->when($typeId > 0, fn ($q) => $q->where('r.routine_type_id', $typeId))
                ->where(function ($q) use ($from, $to) {
                    $q->whereBetween('e.validated_at', [$from->toDateTimeString(), $to->toDateTimeString()])
                        ->orWhereBetween('r.scheduled_at', [$from->toDateTimeString(), $to->toDateTimeString()]);
                })
                ->exists();

            return $exists ? 1 : 0;
        }

        $sku = (string) ($prediction['catalog_item_code'] ?? '');
        $exists = DB::table('routine_consumptions as c')
            ->join('routine_executions as e', 'e.id', '=', 'c.routine_execution_id')
            ->join('routines as r', 'r.id', '=', 'e.routine_id')
            ->join('supply_items as s', 's.id', '=', 'c.supply_item_id')
            ->where('r.company_id', $companyId)
            ->where('r.client_id', $clientId)
            ->whereRaw('LOWER(s.sku) = ?', [strtolower($sku)])
            ->whereBetween('e.validated_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->exists();

        return $exists ? 1 : 0;
    }

    /**
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
     * @param  list<array{label: int, probability: float}>  $scored
     * @return array{threshold: float, precision: float|null, recall: float|null, f1: float|null}
     */
    private function alertMetrics(array $scored, float $threshold): array
    {
        $tp = $fp = $fn = 0;
        foreach ($scored as $row) {
            $alert = $row['probability'] >= $threshold;
            if ($alert && $row['label'] === 1) {
                $tp++;
            } elseif ($alert && $row['label'] === 0) {
                $fp++;
            } elseif (! $alert && $row['label'] === 1) {
                $fn++;
            }
        }
        $precision = ($tp + $fp) > 0 ? round($tp / ($tp + $fp), 4) : null;
        $recall = ($tp + $fn) > 0 ? round($tp / ($tp + $fn), 4) : null;
        $f1 = ($precision !== null && $recall !== null && ($precision + $recall) > 0)
            ? round(2 * $precision * $recall / ($precision + $recall), 4)
            : null;

        return [
            'threshold' => $threshold,
            'precision' => $precision,
            'recall' => $recall,
            'f1' => $f1,
        ];
    }
}
