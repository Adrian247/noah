<?php

namespace App\Services\Billing\Fiscal;

use App\Contracts\Billing\FiscalAdapter;
use App\Models\Invoice;
use App\Services\Billing\FiscalResult;
use Illuminate\Support\Str;

class SandboxPacAdapter implements FiscalAdapter
{
    public function issue(Invoice $invoice): FiscalResult
    {
        $invoice->loadMissing(['lines', 'client', 'company']);
        $uuid = (string) Str::uuid();
        $series = 'SANDBOX';
        $folio = str_pad((string) $invoice->id, 8, '0', STR_PAD_LEFT);

        $xml = $this->buildSandboxCfdiXml($invoice, $uuid, $series, $folio);

        return new FiscalResult(
            success: true,
            uuid: $uuid,
            series: $series,
            folio: $folio,
            xmlContents: $xml,
        );
    }

    public function cancel(Invoice $invoice): FiscalResult
    {
        return new FiscalResult(success: true, uuid: $invoice->fiscal_uuid);
    }

    private function buildSandboxCfdiXml(Invoice $invoice, string $uuid, string $series, string $folio): string
    {
        $emitter = $invoice->company?->tax_id ?? 'XAXX010101000';
        $receiver = $invoice->client?->tax_id ?? 'XAXX010101000';
        $total = number_format((float) $invoice->total, 2, '.', '');
        $issuedAt = now()->toIso8601String();

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" Version="4.0" Serie="{$series}" Folio="{$folio}" Total="{$total}" UUID="{$uuid}" Fecha="{$issuedAt}">
  <cfdi:Emisor Rfc="{$emitter}" Nombre="{$this->xmlEscape($invoice->company?->legal_name ?? $invoice->company?->name ?? 'Emisor')}"/>
  <cfdi:Receptor Rfc="{$receiver}" Nombre="{$this->xmlEscape($invoice->client?->legal_name ?? 'Receptor')}"/>
  <cfdi:Complemento>
    <tfd:TimbreFiscalDigital xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" UUID="{$uuid}"/>
  </cfdi:Complemento>
</cfdi:Comprobante>
XML;
    }

    private function xmlEscape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
