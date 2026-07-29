<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Services\Billing\InvoiceDeliveryPackageBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
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
        $label = $this->invoice->custom_reference ?: $this->invoice->number;

        return new Envelope(
            subject: 'Factura '.$label.' — '.$this->invoice->company?->name,
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

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $package = app(InvoiceDeliveryPackageBuilder::class);
        $built = $package->buildInMemory($this->invoice->loadMissing('evidences'));

        return [
            Attachment::fromData(fn () => $built['bytes'], $built['filename'])
                ->withMime('application/zip'),
        ];
    }
}
