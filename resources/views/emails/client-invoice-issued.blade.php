<x-mail::message>
# Factura disponible

Hola {{ $clientName }},

@php
    $label = $invoice->custom_reference ?: $invoice->number;
@endphp
Se emitió la factura **{{ $label }}**@if($invoice->custom_reference && $invoice->number) (folio {{ $invoice->number }})@endif por un total de **{{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency }}**.

Adjuntamos un archivo ZIP con el PDF de la factura y las evidencias asociadas (incluido el CFDI SAT si fue cargado en prefactura).

@if ($invoice->client_portal_visible)
Puedes consultarla y descargar el mismo paquete desde tu portal de cliente.
@endif

Gracias,<br>
{{ $invoice->company?->name }}
</x-mail::message>
