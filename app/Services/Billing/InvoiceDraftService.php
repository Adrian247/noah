<?php

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Routine;
use App\Models\RoutineExecution;
use App\Support\CurrentCompany;

class InvoiceDraftService
{
    public function createFromRoutine(Routine $routine, RoutineExecution $execution): Invoice
    {
        $company = app(CurrentCompany::class)->company ?? $routine->company;
        $currency = $company?->currency ?? 'MXN';

        $laborHours = max(($execution->duration_minutes ?? 0) / 60, 0);
        $laborRate = 350.00;
        $laborTotal = round($laborHours * $laborRate, 2);

        $consumptionTotal = (float) $execution->consumptions()
            ->get()
            ->sum(fn ($line) => (float) $line->quantity * (float) $line->unit_cost);

        $subtotal = $laborTotal + $consumptionTotal;
        $tax = round($subtotal * 0.16, 2);
        $total = $subtotal + $tax;

        $invoice = Invoice::query()->create([
            'company_id' => $routine->company_id,
            'routine_id' => $routine->id,
            'status' => InvoiceStatus::Draft,
            'currency' => $currency,
            'subtotal' => $subtotal,
            'tax_total' => $tax,
            'total' => $total,
        ]);

        if ($laborTotal > 0) {
            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'description' => 'Mano de obra ('.number_format($laborHours, 2).' h)',
                'quantity' => 1,
                'unit_price' => $laborTotal,
                'line_total' => $laborTotal,
            ]);
        }

        foreach ($execution->consumptions as $consumption) {
            $lineTotal = round((float) $consumption->quantity * (float) $consumption->unit_cost, 2);
            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'description' => $consumption->supplyItem?->name ?? 'Insumo',
                'quantity' => $consumption->quantity,
                'unit_price' => $consumption->unit_cost,
                'line_total' => $lineTotal,
            ]);
        }

        if ($invoice->lines()->count() === 0) {
            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'description' => 'Servicio de mantenimiento rutina #'.$routine->id,
                'quantity' => 1,
                'unit_price' => $subtotal > 0 ? $subtotal : 0,
                'line_total' => $subtotal > 0 ? $subtotal : 0,
            ]);
        }

        return $invoice->load('lines');
    }
}
