<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class InvoicePdfExporter
{
    public function stream(Invoice $invoice): Response
    {
        $invoice->load(['lines', 'client', 'company']);

        $html = view('invoices.pdf', ['invoice' => $invoice])->render();

        return Pdf::loadHTML($html)
            ->setPaper('letter')
            ->download('factura-'.($invoice->number ?? $invoice->id).'.pdf');
    }
}
