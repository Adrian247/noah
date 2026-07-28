<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientInvoiceIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Factura '.$this->invoice->number.' — '.$this->invoice->company?->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.client-invoice-issued',
            with: [
                'invoice' => $this->invoice,
                'clientName' => $this->invoice->client?->trade_name ?? $this->invoice->client?->legal_name,
            ],
        );
    }
}
