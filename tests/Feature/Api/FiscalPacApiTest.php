<?php

namespace Tests\Feature\Api;

use App\Enums\InvoiceEvidenceKind;
use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Services\Billing\FiscalIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesMeinCompany;
use Tests\TestCase;

class FiscalPacApiTest extends TestCase
{
    use RefreshDatabase;
    use UsesMeinCompany;

    public function test_sandbox_fiscal_stamps_invoice_and_attaches_cfdi(): void
    {
        $this->seed();
        $company = $this->meinCompany();
        $company->update([
            'fiscal_enabled' => true,
            'fiscal_provider' => 'sandbox',
        ]);

        $client = Client::query()->where('company_id', $company->id)->firstOrFail();
        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => InvoiceStatus::Draft,
            'currency' => 'MXN',
            'tax_rate_snapshot' => 0.16,
            'subtotal' => 100,
            'tax_total' => 16,
            'total' => 116,
        ]);
        InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'line_type' => 'other',
            'description' => 'Servicio',
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
            'sort_order' => 0,
        ]);

        $result = app(FiscalIssuanceService::class)->stampBeforeIssue($invoice->fresh(['company', 'client', 'lines']));
        $this->assertTrue($result['ok']);

        $invoice->refresh();
        $this->assertNotNull($invoice->fiscal_uuid);
        $this->assertSame('SANDBOX', $invoice->fiscal_series);
        $this->assertDatabaseHas('invoice_evidences', [
            'invoice_id' => $invoice->id,
            'kind' => InvoiceEvidenceKind::SatCfdi->value,
        ]);
    }
}
