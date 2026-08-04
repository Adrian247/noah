<?php

namespace App\Services\Predictive;

use App\Enums\RoutineStatus;
use App\Enums\ServiceCategory;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Predicción de demanda de servicios a cliente (fabricación / instalación)
 * a partir del historial de servicios aplicados (sin artículo obligatorio).
 */
class ClientDemandPredictionService
{
    /**
     * @param  array{
     *     service_category?: string|null,
     *     service_line?: string|null,
     *     client_id?: int|null,
     *     horizon_days?: int|null,
     *     limit?: int|null,
     *     as_of?: string|null
     * }  $filters
     * @return array<string, mixed>
     */
    public function predict(int $companyId, array $filters = []): array
    {
        $horizonDays = max(7, min(90, (int) ($filters['horizon_days'] ?? 30)));
        $limit = max(1, min(100, (int) ($filters['limit'] ?? 20)));
        $asOf = isset($filters['as_of']) && $filters['as_of']
            ? CarbonImmutable::parse($filters['as_of'])
            : CarbonImmutable::today();

        $categories = $this->resolveCategories($filters['service_category'] ?? $filters['service_line'] ?? null);
        $lookbackDays = max(90, $horizonDays * 3);
        $from = $asOf->subDays($lookbackDays)->startOfDay();

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
            ->whereIn('t.service_category', $categories)
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
            ->groupBy('r.client_id', 'r.routine_type_id', 't.service_category', 't.name')
            ->selectRaw(
                'r.client_id,
                 r.routine_type_id,
                 t.service_category,
                 t.name as routine_type_name,
                 COUNT(*) as routines,
                 MAX(COALESCE(e.validated_at, r.scheduled_at)) as last_at,
                 COALESCE(SUM(e.duration_minutes), 0) as duration_minutes'
            )
            ->get();

        if ($rows->isEmpty()) {
            return [
                'as_of' => $asOf->toDateString(),
                'horizon_days' => $horizonDays,
                'lookback_days' => $lookbackDays,
                'service_categories' => $categories,
                'service_lines' => $categories,
                'evaluated' => 0,
                'predictions' => [],
                'notes' => [
                    'No hay servicios validados de fabricación o instalación con cliente en el periodo analizado.',
                ],
            ];
        }

        $clientIds = $rows->pluck('client_id')->map(fn ($id) => (int) $id)->unique()->all();
        $clients = Client::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->whereIn('id', $clientIds)
            ->get(['id', 'trade_name', 'legal_name', 'code'])
            ->keyBy('id');

        $predictions = [];
        foreach ($rows as $row) {
            $last = $row->last_at ? CarbonImmutable::parse((string) $row->last_at) : null;
            $daysSince = $last ? $last->diffInDays($asOf) : $lookbackDays;
            $routines = (int) $row->routines;
            $ratePerDay = $routines / max(1, $lookbackDays);
            $expected = round($ratePerDay * $horizonDays, 3);

            $recencyBoost = 1 + max(0, (45 - $daysSince) / 45);
            $score = round($expected * $recencyBoost, 4);
            $probability = round(min(0.95, max(0.05, 1 - exp(-$score))), 4);

            $category = ServiceCategory::tryFrom((string) $row->service_category) ?? ServiceCategory::Manufacturing;
            $client = $clients->get((int) $row->client_id);
            $predictions[] = [
                'client_id' => (int) $row->client_id,
                'client_name' => $client?->trade_name
                    ?? $client?->legal_name
                    ?? $client?->code
                    ?? 'Cliente #'.$row->client_id,
                'routine_type_id' => (int) $row->routine_type_id,
                'routine_type_name' => (string) $row->routine_type_name,
                'service_category' => $category->value,
                'service_line' => $category->value,
                'service_line_label' => $category->label(),
                'routines_in_lookback' => $routines,
                'last_routine_at' => $last?->toDateString(),
                'days_since_last' => $daysSince,
                'expected_requests' => $expected,
                'probability' => $probability,
                'score' => $score,
                'drivers' => [
                    [
                        'code' => 'frecuencia',
                        'label' => 'Frecuencia histórica',
                        'evidence' => sprintf(
                            '%d servicios en %d días (%.2f / día).',
                            $routines,
                            $lookbackDays,
                            $ratePerDay,
                        ),
                    ],
                    [
                        'code' => 'recencia',
                        'label' => 'Recencia',
                        'evidence' => $last
                            ? sprintf('Último servicio el %s (hace %d días).', $last->toDateString(), $daysSince)
                            : 'Sin fecha de último servicio.',
                    ],
                ],
            ];
        }

        usort($predictions, fn (array $a, array $b) => $b['score'] <=> $a['score']);
        $ranked = array_slice($predictions, 0, $limit);

        return [
            'as_of' => $asOf->toDateString(),
            'horizon_days' => $horizonDays,
            'lookback_days' => $lookbackDays,
            'service_categories' => $categories,
            'service_lines' => $categories,
            'evaluated' => count($predictions),
            'returned' => count($ranked),
            'predictions' => $ranked,
            'notes' => [],
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveCategories(?string $line): array
    {
        if ($line === null || trim($line) === '' || $line === 'demand') {
            return [ServiceCategory::Manufacturing->value, ServiceCategory::Installation->value];
        }

        $legacyMap = [
            'fabrication' => ServiceCategory::Manufacturing->value,
            'supply' => ServiceCategory::Manufacturing->value,
        ];
        if (isset($legacyMap[$line])) {
            return [$legacyMap[$line]];
        }

        $parsed = ServiceCategory::tryFrom($line);
        if ($parsed === ServiceCategory::Manufacturing || $parsed === ServiceCategory::Installation) {
            return [$parsed->value];
        }

        return [ServiceCategory::Manufacturing->value, ServiceCategory::Installation->value];
    }
}
