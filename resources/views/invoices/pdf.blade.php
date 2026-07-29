<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Factura {{ $invoice->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f1f5f9; }
        .totals { margin-top: 12px; text-align: right; }
    </style>
</head>
<body>
    <h1>Factura {{ $invoice->number }}</h1>
    @if ($invoice->custom_reference)
        <p><strong>Referencia:</strong> {{ $invoice->custom_reference }}</p>
    @endif
    <p><strong>ID interno:</strong> #{{ $invoice->id }}</p>
    <p><strong>Cliente:</strong> {{ $invoice->client?->legal_name }}</p>
    <p><strong>Fecha emisión:</strong> {{ $invoice->issued_at?->format('d/m/Y H:i') }}</p>
    <table>
        <thead>
            <tr>
                <th>Descripción</th>
                <th>Cant.</th>
                <th>P. unit.</th>
                <th>Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td>{{ $line->quantity }}</td>
                    <td>{{ number_format((float) $line->unit_price, 2) }}</td>
                    <td>{{ number_format((float) $line->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="totals">
        <p>Subtotal: {{ number_format((float) $invoice->subtotal, 2) }} {{ $invoice->currency }}</p>
        <p>IVA: {{ number_format((float) $invoice->tax_total, 2) }}</p>
        <p><strong>Total: {{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency }}</strong></p>
    </div>
</body>
</html>
