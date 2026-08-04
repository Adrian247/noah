<?php

namespace App\Services\Billing;

use App\Enums\InvoiceLineType;
use App\Enums\InvoiceStatus;
use App\Enums\ServiceCategory;
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
        $routine->loadMissing(['company', 'routineType']);
        $company = app(CurrentCompany::class)->company ?? $routine->company;
        $currency = $company?->currency ?? 'MXN';

        $laborRate = (float) ($company?->billing_labor_rate_per_hour
            ?? config('phoenix.billing.labor_rate_per_hour', 0));
        $taxRate = (float) ($company?->billing_tax_rate
            ?? config('phoenix.billing.tax_rate', 0.16));

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
        $category = $routine->routineType?->service_category;
        $isManufacturing = $category instanceof ServiceCategory && $category === ServiceCategory::Manufacturing;

        foreach ($execution->consumptions as $consumption) {
            $qty = (float) $consumption->quantity;
            $unit = (float) $consumption->unit_cost;
            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'line_type' => InvoiceLineType::Supply,
                'sort_order' => $sort++,
                'source_routine_consumption_id' => $consumption->id,
                'description' => $isManufacturing
                    ? 'Material fabricación: '.($consumption->supplyItem?->name ?? 'Insumo')
                    : ($consumption->supplyItem?->name ?? 'Insumo'),
                'quantity' => $qty,
                'unit_price' => $unit,
                'line_total' => $this->totals->lineTotal($qty, $unit),
            ]);
        }

        if ($invoice->lines()->count() === 0) {
            $fallback = match (true) {
                $isManufacturing => 'Servicio de fabricación #'.$routine->id,
                $category instanceof ServiceCategory && $category === ServiceCategory::Installation => 'Servicio de instalación #'.$routine->id,
                default => 'Servicio #'.$routine->id,
            };
            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'line_type' => InvoiceLineType::Other,
                'sort_order' => 0,
                'description' => $fallback,
                'quantity' => 1,
                'unit_price' => 0,
                'line_total' => 0,
            ]);
        }

        return $this->totals->applyTaxRate($invoice->fresh(['lines']), $taxRate);
    }
}