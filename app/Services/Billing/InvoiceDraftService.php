<?php

namespace App\Services\Billing;

use App\Enums\InvoiceLineType;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Routine;
use App\Models\RoutineExecution;
use App\Support\CurrentCompany;

class InvoiceDraftService
{
    public function __construct(
        private readonly InvoiceTotalsCalculator $totals,
    ) {}

    public function createFromRoutine(Routine $routine, RoutineExecution $execution): Invoice
    {
        $company = app(CurrentCompany::class)->company ?? $routine->company;
        $currency = $company?->currency ?? 'MXN';

        $laborRate = (float) ($company?->billing_labor_rate_per_hour
            ?? config('noah.billing.labor_rate_per_hour', 0));
        $taxRate = (float) ($company?->billing_tax_rate
            ?? config('noah.billing.tax_rate', 0.16));

        $laborHours = max(($execution->duration_minutes ?? 0) / 60, 0);

        $invoice = Invoice::query()->create([
            'company_id' => $routine->company_id,
            'routine_id' => $routine->id,
            'client_id' => app(RoutineInvoiceClientResolver::class)->resolveForRoutine($routine),
            'status' => InvoiceStatus::Draft,
            'currency' => $currency,
            'tax_rate_snapshot' => $taxRate,
            'subtotal' => 0,
            'tax_total' => 0,
            'total' => 0,
        ]);

        $sort = 0;

        if ($laborHours > 0 && $laborRate > 0) {
            $lineTotal = $this->totals->lineTotal(1, round($laborHours * $laborRate, 2));
            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'line_type' => InvoiceLineType::Labor,
                'sort_order' => $sort++,
                'description' => 'Mano de obra sugerida',
                'quantity' => 1,
                'unit_price' => $lineTotal,
                'line_total' => $lineTotal,
                'metadata' => [
                    'workers' => 1,
                    'hours' => round($laborHours, 4),
                    'rate_per_hour' => $laborRate,
                ],
            ]);
        }

        $execution->loadMissing(['consumptions.supplyItem']);
        foreach ($execution->consumptions as $consumption) {
            $qty = (float) $consumption->quantity;
            $unit = (float) $consumption->unit_cost;
            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'line_type' => InvoiceLineType::Supply,
                'sort_order' => $sort++,
                'source_routine_consumption_id' => $consumption->id,
                'description' => $consumption->supplyItem?->name ?? 'Insumo',
                'quantity' => $qty,
                'unit_price' => $unit,
                'line_total' => $this->totals->lineTotal($qty, $unit),
            ]);
        }

        if ($invoice->lines()->count() === 0) {
            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'line_type' => InvoiceLineType::Other,
                'sort_order' => 0,
                'description' => 'Servicio de mantenimiento rutina #'.$routine->id,
                'quantity' => 1,
                'unit_price' => 0,
                'line_total' => 0,
            ]);
        }

        return $this->totals->applyTaxRate($invoice->fresh(['lines']), $taxRate);
    }
}