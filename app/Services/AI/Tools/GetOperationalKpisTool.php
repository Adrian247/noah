<?php

namespace App\Services\AI\Tools;

use App\Enums\InvoiceStatus;
use App\Enums\RoutineStatus;
use App\Models\Asset;
use App\Models\Invoice;
use App\Models\Routine;
use App\Services\AI\Contracts\AiTool;

/**
 * Agrega KPIs operativos determinísticos (sin LLM) para dashboards del asistente.
 */
class GetOperationalKpisTool implements AiTool
{
    public function name(): string
    {
        return 'get_operational_kpis';
    }

    public function description(): string
    {
        return 'Obtiene KPIs agregados de la empresa: servicios por estado, facturas y activos. Úsalo para dashboards o preguntas de indicadores.';
    }

    public function requiredPermissions(): array
    {
        return [
            'routines.execute',
            'routines.assign',
            'routines.validate',
            'costs.view',
            'billing.draft',
            'insights.use',
        ];
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass,
        ];
    }

    public function execute(array $arguments, int $companyId): array
    {
        $routinesByStatus = Routine::query()
            ->where('company_id', $companyId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($n) => (int) $n)
            ->all();

        $routineTotal = array_sum($routinesByStatus);
        $completedStatuses = [
            RoutineStatus::Validated->value ?? 'validated',
            'validated',
            'completed',
            'closed',
        ];
        $completed = 0;
        foreach ($routinesByStatus as $status => $count) {
            if (in_array((string) $status, $completedStatuses, true)) {
                $completed += $count;
            }
        }

        $invoicesByStatus = Invoice::query()
            ->where('company_id', $companyId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($n) => (int) $n)
            ->all();

        $issuedTotal = (float) Invoice::query()
            ->where('company_id', $companyId)
            ->where('status', InvoiceStatus::Issued->value)
            ->sum('total');

        $assetsCount = Asset::query()->where('company_id', $companyId)->count();

        $data = [
            'routines' => [
                'total' => $routineTotal,
                'by_status' => $routinesByStatus,
                'completed' => $completed,
                'completion_pct' => $routineTotal > 0 ? round($completed / $routineTotal * 100, 1) : 0.0,
            ],
            'invoices' => [
                'by_status' => $invoicesByStatus,
                'issued_amount' => $issuedTotal,
            ],
            'assets' => [
                'total' => $assetsCount,
            ],
        ];

        return [
            'data' => $data,
            'sources' => [[
                'type' => 'kpi',
                'id' => $companyId,
                'label' => 'KPIs operativos empresa #'.$companyId,
            ]],
        ];
    }
}
