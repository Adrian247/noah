<x-mail::message>
# Factura disponible

Hola {{ $clientName }},

Se emitió la factura **{{ $invoice->number }}** por un total de **{{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency }}**.

@if ($invoice->client_portal_visible)
Puedes consultarla y descargarla desde tu portal de cliente.
@endif

Gracias,<br>
{{ $invoice->company?->name }}
</x-mail::message>
