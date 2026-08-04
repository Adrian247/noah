<?php

namespace App\Services\AI;

/**
 * Construye artefactos tipo dashboard (Taag-compatible) de forma determinística.
 */
class AssistantDashboardBuilder
{
    public function wantsDashboard(string $question): bool
    {
        $normalized = mb_strtolower($question);

        foreach (['dashboard', 'kpi', 'indicador', 'tablero', 'métrica', 'metrica', 'resumen operativo', 'gráfica', 'grafica'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $kpiData  salida de get_operational_kpis
     * @return array{type: string, title: string, content: array<string, mixed>}|null
     */
    public function fromOperationalKpis(array $kpiData): ?array
    {
        $routines = is_array($kpiData['routines'] ?? null) ? $kpiData['routines'] : [];
        $invoices = is_array($kpiData['invoices'] ?? null) ? $kpiData['invoices'] : [];
        $assets = is_array($kpiData['assets'] ?? null) ? $kpiData['assets'] : [];

        $totalRoutines = (int) ($routines['total'] ?? 0);
        if ($totalRoutines === 0 && empty($invoices['by_status'] ?? []) && (int) ($assets['total'] ?? 0) === 0) {
            return null;
        }

        $byStatus = is_array($routines['by_status'] ?? null) ? $routines['by_status'] : [];
        $statusMetrics = [];
        foreach ($byStatus as $status => $count) {
            $statusMetrics[] = [
                'title' => (string) $status,
                'value' => (int) $count,
                'unit' => 'servicios',
            ];
        }

        $invoiceMetrics = [];
        foreach (($invoices['by_status'] ?? []) as $status => $count) {
            $invoiceMetrics[] = [
                'title' => (string) $status,
                'value' => (int) $count,
                'unit' => 'facturas',
            ];
        }

        $charts = [
            [
                'type' => 'kpi',
                'title' => 'Servicios',
                'value' => $totalRoutines,
                'unit' => 'total',
                'hero' => true,
                'layout' => ['colSpan' => 4],
            ],
            [
                'type' => 'kpi',
                'title' => 'Completitud',
                'value' => (float) ($routines['completion_pct'] ?? 0),
                'unit' => '%',
                'layout' => ['colSpan' => 4],
            ],
            [
                'type' => 'kpi',
                'title' => 'Activos',
                'value' => (int) ($assets['total'] ?? 0),
                'unit' => 'equipos',
                'layout' => ['colSpan' => 4],
            ],
        ];

        if ($statusMetrics !== []) {
            $charts[] = [
                'type' => 'kpi-grid',
                'title' => 'Servicios por estado',
                'metrics' => $statusMetrics,
                'layout' => ['colSpan' => 12],
            ];
        }

        if ($invoiceMetrics !== []) {
            $charts[] = [
                'type' => 'kpi-grid',
                'title' => 'Facturas por estado',
                'metrics' => $invoiceMetrics,
                'layout' => ['colSpan' => 12],
            ];
            $charts[] = [
                'type' => 'kpi',
                'title' => 'Emitido',
                'value' => round((float) ($invoices['issued_amount'] ?? 0), 2),
                'unit' => 'MXN',
                'layout' => ['colSpan' => 6],
            ];
        }

        $tableRows = [];
        foreach ($byStatus as $status => $count) {
            $tableRows[] = [(string) $status, (string) $count];
        }
        if ($tableRows !== []) {
            $charts[] = [
                'type' => 'table',
                'title' => 'Detalle servicios',
                'data' => [
                    'headers' => ['Estado', 'Cantidad'],
                    'rows' => $tableRows,
                ],
                'layout' => ['colSpan' => 12],
            ];
        }

        return [
            'type' => 'dashboard',
            'title' => 'KPIs operativos',
            'content' => [
                'layout' => ['columns' => 12],
                'charts' => $charts,
            ],
        ];
    }

    /**
     * Intenta construir dashboard desde resultados de tools arbitrarios.
     *
     * @param  list<array{name: string, data?: mixed}>  $toolPayloads
     * @return array{type: string, title: string, content: array<string, mixed>}|null
     */
    public function fromToolPayloads(array $toolPayloads): ?array
    {
        foreach ($toolPayloads as $payload) {
            if (($payload['name'] ?? '') === 'get_operational_kpis' && is_array($payload['data'] ?? null)) {
                return $this->fromOperationalKpis($payload['data']);
            }
        }

        return null;
    }
}
