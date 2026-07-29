<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class InvoicePdfExporter
{
    public function contents(Invoice $invoice): string
    {
        $invoice->loadMissing(['lines', 'client', 'company']);

        $html = view('invoices.pdf', ['invoice' => $invoice])->render();

        return Pdf::loadHTML($html)
            ->setPaper('letter')
            ->output();
    }

    public function stream(Invoice $invoice): Response
    {
        $filename = 'factura-'.($invoice->number ?? $invoice->id).'.pdf';

        return response($this->contents($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
