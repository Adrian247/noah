<?php

namespace App\Services\Predictive;

use App\Enums\RoutineStatus;
use App\Enums\ServiceLine;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Predicción de demanda de servicios a cliente (manufactura / suministro)
 * a partir del historial de rutinas aplicadas (sin activo obligatorio).
 */
class ClientDemandPredictionService
{
    /**
     * @param  array{
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

        $lines = $this->resolveLines($filters['service_line'] ?? null);
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
            ->whereIn('t.service_line', $lines)
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
            ->groupBy('r.client_id', 'r.routine_type_id', 't.service_line', 't.name')
            ->selectRaw(
                'r.client_id,
                 r.routine_type_id,
                 t.service_line,
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
                'service_lines' => $lines,
                'evaluated' => 0,
                'predictions' => [],
                'notes' => [
                    'No hay rutinas validadas de manufactura o suministro con cliente en el periodo analizado.',
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

            // Recencia: más reciente ⇒ mayor score; frecuencia refuerza.
            $recencyBoost = 1 + max(0, (45 - $daysSince) / 45);
            $score = round($expected * $recencyBoost, 4);
            $probability = round(min(0.95, max(0.05, 1 - exp(-$score))), 4);

            $client = $clients->get((int) $row->client_id);
            $predictions[] = [
                'client_id' => (int) $row->client_id,
                'client_name' => $client?->trade_name
                    ?? $client?->legal_name
                    ?? $client?->code
                    ?? 'Cliente #'.$row->client_id,
                'routine_type_id' => (int) $row->routine_type_id,
                'routine_type_name' => (string) $row->routine_type_name,
                'service_line' => (string) $row->service_line,
                'service_line_label' => ServiceLine::tryFrom((string) $row->service_line)?->label()
                    ?? (string) $row->service_line,
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
                            '%d rutinas en %d días (%.2f / día).',
                            $routines,
                            $lookbackDays,
                            $ratePerDay,
                        ),
                    ],
                    [
                        'code' => 'recencia',
                        'label' => 'Recencia',
                        'evidence' => $last
                            ? sprintf('Última rutina el %s (hace %d días).', $last->toDateString(), $daysSince)
                            : 'Sin fecha de última rutina.',
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
            'service_lines' => $lines,
            'evaluated' => count($predictions),
            'returned' => count($ranked),
            'predictions' => $ranked,
            'notes' => [],
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveLines(?string $line): array
    {
        if ($line === null || trim($line) === '' || $line === 'demand') {
            return [ServiceLine::Fabrication->value, ServiceLine::Supply->value];
        }

        $parsed = ServiceLine::tryFrom($line);
        if ($parsed === ServiceLine::Fabrication || $parsed === ServiceLine::Supply) {
            return [$parsed->value];
        }

        return [ServiceLine::Fabrication->value, ServiceLine::Supply->value];
    }
}
