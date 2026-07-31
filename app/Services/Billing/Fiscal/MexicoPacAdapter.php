<?php

namespace App\Services\Billing\Fiscal;

use App\Contracts\Billing\FiscalAdapter;
use App\Models\Invoice;
use App\Services\Billing\FiscalResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MexicoPacAdapter implements FiscalAdapter
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        private readonly array $settings = [],
    ) {}

    public function issue(Invoice $invoice): FiscalResult
    {
        $baseUrl = (string) ($this->settings['base_url'] ?? config('phoenix.billing.fiscal.mexico_pac.base_url', ''));
        if ($baseUrl === '') {
            return new FiscalResult(success: false, error: 'PAC México: configure base_url en fiscal_settings o PHOENIX_PAC_BASE_URL.');
        }

        $invoice->loadMissing(['lines', 'client', 'company']);
        $apiKey = (string) ($this->settings['api_key'] ?? config('phoenix.billing.fiscal.mexico_pac.api_key', ''));

        try {
            $response = Http::withToken($apiKey !== '' ? $apiKey : null)
                ->timeout(60)
                ->accept('application/json')
                ->post(rtrim($baseUrl, '/').'/stamp', [
                    'invoice_id' => $invoice->id,
                    'emitter_rfc' => $invoice->company?->tax_id,
                    'receiver_rfc' => $invoice->client?->tax_id,
                    'receiver_name' => $invoice->client?->legal_name,
                    'currency' => $invoice->currency,
                    'subtotal' => (float) $invoice->subtotal,
                    'tax_total' => (float) $invoice->tax_total,
                    'total' => (float) $invoice->total,
                    'lines' => $invoice->lines->map(fn ($line) => [
                        'description' => $line->description,
                        'quantity' => (float) $line->quantity,
                        'unit_price' => (float) $line->unit_price,
                        'line_total' => (float) $line->line_total,
                    ])->values()->all(),
                ]);

            if (! $response->successful()) {
                return new FiscalResult(
                    success: false,
                    error: 'PAC respondió '.$response->status().': '.Str::limit($response->body(), 500),
                );
            }

            $data = $response->json();
            $xml = is_string($data['xml'] ?? null) ? $data['xml'] : null;
            if ($xml === null || trim($xml) === '') {
                return new FiscalResult(success: false, error: 'PAC no devolvió XML timbrado.');
            }

            return new FiscalResult(
                success: true,
                uuid: (string) ($data['uuid'] ?? ''),
                series: isset($data['series']) ? (string) $data['series'] : null,
                folio: isset($data['folio']) ? (string) $data['folio'] : null,
                xmlContents: $xml,
            );
        } catch (\Throwable $e) {
            report($e);

            return new FiscalResult(success: false, error: $e->getMessage());
        }
    }

    public function cancel(Invoice $invoice): FiscalResult
    {
        $baseUrl = (string) ($this->settings['base_url'] ?? config('phoenix.billing.fiscal.mexico_pac.base_url', ''));
        if ($baseUrl === '' || $invoice->fiscal_uuid === null) {
            return new FiscalResult(success: false, error: 'No hay UUID fiscal para cancelar.');
        }

        $apiKey = (string) ($this->settings['api_key'] ?? config('phoenix.billing.fiscal.mexico_pac.api_key', ''));

        try {
            $response = Http::withToken($apiKey !== '' ? $apiKey : null)
                ->timeout(60)
                ->post(rtrim($baseUrl, '/').'/cancel', [
                    'uuid' => $invoice->fiscal_uuid,
                ]);

            if (! $response->successful()) {
                return new FiscalResult(success: false, error: 'Cancelación PAC: '.$response->status());
            }

            return new FiscalResult(success: true, uuid: $invoice->fiscal_uuid);
        } catch (\Throwable $e) {
            report($e);

            return new FiscalResult(success: false, error: $e->getMessage());
        }
    }
}
