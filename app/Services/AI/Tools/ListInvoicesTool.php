<?php

namespace App\Services\AI\Tools;

use App\Models\Invoice;
use App\Services\AI\Contracts\AiTool;

class ListInvoicesTool implements AiTool
{
    public function name(): string
    {
        return 'list_invoices';
    }

    public function description(): string
    {
        return 'Lista facturas recientes de la empresa (solo lectura): número, estado, total y cliente.';
    }

    public function requiredPermissions(): array
    {
        return ['billing.draft', 'billing.issue', 'billing.settings', 'costs.view'];
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'description' => 'Filtro opcional: draft, issued, cancelled, fiscal_error.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Máximo de facturas (1-20). Default 10.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, int $companyId): array
    {
        $limit = max(1, min(20, (int) ($arguments['limit'] ?? 10)));
        $status = trim((string) ($arguments['status'] ?? ''));

        $builder = Invoice::query()
            ->where('company_id', $companyId)
            ->with('client')
            ->orderByDesc('updated_at');

        if ($status !== '') {
            $builder->where('status', $status);
        }

        $invoices = $builder->limit($limit)->get();
        $sources = [];
        $data = $invoices->map(function (Invoice $invoice) use (&$sources) {
            $sources[] = [
                'type' => 'invoice',
                'id' => $invoice->id,
                'label' => ($invoice->number ?: 'Factura #'.$invoice->id).' · '.($invoice->status->value ?? (string) $invoice->status),
            ];

            return [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status->value ?? (string) $invoice->status,
                'total' => (float) $invoice->total,
                'currency' => $invoice->currency,
                'client' => $invoice->client?->trade_name ?: $invoice->client?->legal_name,
                'issued_at' => $invoice->issued_at?->toDateTimeString(),
            ];
        })->all();

        return ['data' => $data, 'sources' => $sources];
    }
}
