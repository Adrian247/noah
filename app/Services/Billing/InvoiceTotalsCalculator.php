<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\InvoiceLine;

class InvoiceTotalsCalculator
{
    public function lineTotal(float $quantity, float $unitPrice): float
    {
        return round($quantity * $unitPrice, 2);
    }

    public function applyTaxRate(Invoice $invoice, float $taxRate): Invoice
    {
        $invoice->loadMissing('lines');

        $subtotal = round(
            $invoice->lines->sum(fn (InvoiceLine $line) => (float) $line->line_total),
            2,
        );
        $taxTotal = round($subtotal * $taxRate, 2);
        $total = round($subtotal + $taxTotal, 2);

        $invoice->update([
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => $total,
            'tax_rate_snapshot' => $taxRate,
        ]);

        return $invoice->fresh(['lines', 'client']);
    }
}
