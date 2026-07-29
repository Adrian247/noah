<?php

namespace App\Services\Billing;

use App\Mail\ClientInvoiceIssuedMail;
use App\Models\Invoice;
use App\Services\Audit\AuditLogger;
use App\Support\AuditCorrelation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class InvoiceClientDeliveryService
{
    public function bindWorkflowAuditCorrelation(Invoice $invoice): void
    {
        $invoice->loadMissing('routine.workflowInstance');
        AuditCorrelation::set(
            AuditCorrelation::forWorkflowInstance($invoice->routine?->workflowInstance),
        );
    }

    /**
     * Entrega documentación al cliente (portal y/o email) tras la emisión.
     *
     * @param  array{notify_client: bool, client_portal_visible: bool}  $options
     */
    public function deliver(Invoice $invoice, array $options, Request $request, AuditLogger $audit): Invoice
    {
        $notify = $options['notify_client'];
        $portalVisible = $options['client_portal_visible'];

        if (! $notify && ! $portalVisible) {
            throw ValidationException::withMessages([
                'delivery' => ['Seleccione al menos notificación por email o visibilidad en portal.'],
            ]);
        }

        $invoice->loadMissing('client');
        if ($notify && empty($invoice->client?->billing_email)) {
            throw ValidationException::withMessages([
                'notify_client' => ['El cliente no tiene email de facturación.'],
            ]);
        }

        $this->bindWorkflowAuditCorrelation($invoice);

        $wasDeferred = (bool) $invoice->delivery_deferred;
        $hadDelivery = $invoice->delivered_to_client_at !== null;

        $invoice->update([
            'notify_client_on_issue' => $notify || (bool) $invoice->notify_client_on_issue,
            'client_portal_visible' => $portalVisible,
            'delivery_deferred' => false,
            'delivered_to_client_at' => now(),
        ]);

        $fresh = $invoice->fresh(['lines', 'client', 'company', 'evidences']);

        $audit->fromRequest($request, 'invoice.delivered_to_client', Invoice::class, $invoice->id, [
            'notify_client' => $notify,
            'client_portal_visible' => $portalVisible,
            'delivery_deferred_cleared' => $wasDeferred,
            'first_delivery' => ! $hadDelivery,
        ]);

        if ($notify && $fresh->client?->billing_email) {
            Mail::to($fresh->client->billing_email)->queue(
                new ClientInvoiceIssuedMail($fresh),
            );
        }

        return $fresh;
    }
}
