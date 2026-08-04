<?php

namespace Tests\Feature\Api;

use App\Enums\InvoiceStatus;
use App\Models\AuditEntry;
use App\Mail\ClientInvoiceIssuedMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\Routines\DemoRoutineFactory;
use App\Models\RoutineExecution;
use App\Models\User;
use App\Services\Billing\InvoiceDraftService;
use App\Support\CurrentCompany;
use App\Services\Identity\CompanyAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\UsesMeinCompany;
use Tests\TestCase;

class InvoicePrefacturaApiTest extends TestCase
{
    use RefreshDatabase;
    use UsesMeinCompany;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(CompanyAuthorizationService::class)->bootstrapAllCompanies();
    }

    public function test_billing_can_edit_draft_lines(): void
    {
        $company = $this->meinCompany();
        $billing = $this->meinUser('billing@sandbox-demo.com');
        $technician = $this->meinUser('technician@sandbox-demo.com');
        $routine = app(DemoRoutineFactory::class)->createForCompany($company->id, $technician);
        $execution = $routine->latestExecution ?? $routine->executions()->create([
            'performed_by' => $technician->id,
            'duration_minutes' => 60,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        app()->instance(CurrentCompany::class, new CurrentCompany($company));
        $invoice = app(InvoiceDraftService::class)->createFromRoutine($routine, $execution);

        Sanctum::actingAs($billing);

        $response = $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson("/api/v1/billing/invoices/{$invoice->id}/draft", [
                'client_id' => Client::query()->first()->id,
                'lines' => [
                    [
                        'line_type' => 'supply',
                        'description' => 'Filtro ajustado',
                        'quantity' => 2,
                        'unit_price' => 400,
                    ],
                    [
                        'line_type' => 'labor',
                        'description' => 'Dos técnicos',
                        'quantity' => 1,
                        'unit_price' => 1500,
                        'metadata' => ['workers' => 2, 'hours' => 3, 'rate_per_hour' => 250],
                    ],
                ],
            ])
            ->assertOk();

        $this->assertEqualsWithDelta(2300.0, (float) $response->json('data.subtotal'), 0.01);
    }

    public function test_billing_saves_custom_reference_on_draft(): void
    {
        $company = $this->meinCompany();
        $billing = $this->meinUser('billing@sandbox-demo.com');
        $technician = $this->meinUser('technician@sandbox-demo.com');
        $routine = app(DemoRoutineFactory::class)->createForCompany($company->id, $technician);
        $execution = $routine->latestExecution ?? $routine->executions()->create([
            'performed_by' => $technician->id,
            'duration_minutes' => 60,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        app()->instance(CurrentCompany::class, new CurrentCompany($company));
        $invoice = app(InvoiceDraftService::class)->createFromRoutine($routine, $execution);
        $client = Client::query()->where('company_id', $company->id)->first();

        Sanctum::actingAs($billing);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson("/api/v1/billing/invoices/{$invoice->id}/draft", [
                'client_id' => $client->id,
                'custom_reference' => 'Proyecto Torre B',
                'lines' => [
                    [
                        'line_type' => 'other',
                        'description' => 'Servicio',
                        'quantity' => 1,
                        'unit_price' => 1000,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.custom_reference', 'Proyecto Torre B');
    }

    public function test_billing_downloads_zip_package_for_issued_invoice(): void
    {
        $company = $this->meinCompany();
        $billing = $this->meinUser('billing@sandbox-demo.com');
        $technician = $this->meinUser('technician@sandbox-demo.com');
        $routine = app(DemoRoutineFactory::class)->createForCompany($company->id, $technician);
        $client = Client::query()->where('company_id', $company->id)->first();

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'routine_id' => $routine->id,
            'client_id' => $client->id,
            'status' => InvoiceStatus::Issued,
            'number' => 'MEIN-2026-001',
            'custom_reference' => 'Proyecto Torre B',
            'currency' => 'MXN',
            'tax_rate_snapshot' => 0.16,
            'subtotal' => 100,
            'tax_total' => 16,
            'total' => 116,
            'issued_at' => now(),
            'client_portal_visible' => true,
        ]);
        $invoice->lines()->create([
            'line_type' => 'other',
            'description' => 'Servicio',
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        Sanctum::actingAs($billing);

        $response = $this->withHeader('X-Company-Id', (string) $company->id)
            ->get("/api/v1/billing/invoices/{$invoice->id}/package");

        $response->assertOk();
        $this->assertStringContainsString('zip', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('proyecto-torre-b-paquete.zip', (string) $response->headers->get('content-disposition'));

        $file = $response->baseResponse->getFile();
        $this->assertNotNull($file);
        $zipPath = $file->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);
        $pdfFound = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && str_ends_with(strtolower($name), '.pdf')) {
                $pdfFound = true;
                break;
            }
        }
        $zip->close();
        $this->assertTrue($pdfFound, 'El ZIP debe incluir el PDF de la factura.');
    }

    public function test_portal_client_downloads_zip_package(): void
    {
        $company = $this->meinCompany();
        $portalUser = $this->meinUser('cliente.portal@sandbox-demo.com');
        $technician = $this->meinUser('technician@sandbox-demo.com');
        $routine = app(DemoRoutineFactory::class)->createForCompany($company->id, $technician);
        $client = Client::query()->where('company_id', $company->id)->first();

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'routine_id' => $routine->id,
            'client_id' => $client->id,
            'status' => InvoiceStatus::Issued,
            'number' => 'MEIN-2026-002',
            'currency' => 'MXN',
            'tax_rate_snapshot' => 0.16,
            'subtotal' => 50,
            'tax_total' => 8,
            'total' => 58,
            'issued_at' => now(),
            'client_portal_visible' => true,
        ]);

        Sanctum::actingAs($portalUser);

        $response = $this->withHeader('X-Company-Id', (string) $company->id)
            ->get("/api/v1/portal/invoices/{$invoice->id}/download");

        $response->assertOk();
        $this->assertStringContainsString('zip', (string) $response->headers->get('content-type'));
    }

    public function test_billing_can_attach_routine_report_to_draft(): void
    {
        Storage::fake('local');
        config(['phoenix.reports.disk' => 'local']);

        $company = $this->meinCompany();
        $billing = $this->meinUser('billing@sandbox-demo.com');
        $technician = $this->meinUser('technician@sandbox-demo.com');
        $routine = app(DemoRoutineFactory::class)->createForCompany($company->id, $technician);
        $execution = $routine->latestExecution ?? $routine->executions()->create([
            'performed_by' => $technician->id,
            'duration_minutes' => 60,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        app()->instance(CurrentCompany::class, new CurrentCompany($company));
        $invoice = app(InvoiceDraftService::class)->createFromRoutine($routine, $execution);

        $path = 'reports/test-report.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 fake');

        $report = \App\Models\GeneratedReport::query()->create([
            'company_id' => $company->id,
            'routine_id' => $routine->id,
            'routine_execution_id' => $execution->id,
            'status' => 'ready',
            'disk' => 'local',
            'path' => $path,
            'mime' => 'application/pdf',
        ]);

        Sanctum::actingAs($billing);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/billing/invoices/{$invoice->id}/evidences", [
                'kind' => 'routine_report',
                'generated_report_id' => $report->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'routine_report')
            ->assertJsonPath('data.generated_report_id', $report->id);
    }

    public function test_billing_can_upload_supporting_and_sat_evidence_on_draft(): void
    {
        Storage::fake('evidence');
        config(['phoenix.evidence.disk' => 'evidence']);

        $company = $this->meinCompany();
        $billing = $this->meinUser('billing@sandbox-demo.com');
        $technician = $this->meinUser('technician@sandbox-demo.com');
        $routine = app(DemoRoutineFactory::class)->createForCompany($company->id, $technician);
        $execution = $routine->latestExecution ?? $routine->executions()->create([
            'performed_by' => $technician->id,
            'duration_minutes' => 60,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        app()->instance(CurrentCompany::class, new CurrentCompany($company));
        $invoice = app(InvoiceDraftService::class)->createFromRoutine($routine, $execution);

        Sanctum::actingAs($billing);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->post("/api/v1/billing/invoices/{$invoice->id}/evidences", [
                'kind' => 'supporting',
                'file' => \Illuminate\Http\UploadedFile::fake()->image('foto.jpg'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'supporting');

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->post("/api/v1/billing/invoices/{$invoice->id}/evidences", [
                'kind' => 'sat_cfdi',
                'file' => \Illuminate\Http\UploadedFile::fake()->create('cfdi.xml', 50, 'application/xml'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'sat_cfdi');

        $show = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson("/api/v1/billing/invoices/{$invoice->id}")
            ->assertOk();

        $this->assertCount(2, $show->json('evidences'));

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->post("/api/v1/billing/invoices/{$invoice->id}/evidences", [
                'kind' => 'sat_cfdi',
                'file' => \Illuminate\Http\UploadedFile::fake()->create('cfdi-nuevo.pdf', 50, 'application/pdf'),
            ])
            ->assertCreated();

        $this->assertSame(
            1,
            \App\Models\InvoiceEvidence::query()->where('invoice_id', $invoice->id)->where('kind', 'sat_cfdi')->count(),
        );
    }

    public function test_issue_requires_client(): void
    {
        $company = $this->meinCompany();
        $billing = $this->meinUser('billing@sandbox-demo.com');
        $technician = $this->meinUser('technician@sandbox-demo.com');
        $routine = app(DemoRoutineFactory::class)->createForCompany($company->id, $technician);
        app(\App\Services\Workflow\WorkflowRuntime::class)->ensureInstance($routine->load('routineType.workflowDefinition'));
        $routine->update(['status' => \App\Enums\RoutineStatus::PendingBilling]);
        $routine->workflowInstance?->update(['current_step_key' => 'billing_review']);
        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'routine_id' => $routine->id,
            'client_id' => null,
            'status' => InvoiceStatus::Draft,
            'currency' => 'MXN',
            'tax_rate_snapshot' => 0.16,
            'subtotal' => 100,
            'tax_total' => 16,
            'total' => 116,
        ]);
        $invoice->lines()->create([
            'line_type' => 'other',
            'description' => 'Test',
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        Sanctum::actingAs($billing);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/billing/invoices/{$invoice->id}/issue")
            ->assertStatus(422);
    }

    public function test_deliver_to_client_after_deferred_issue_audits_with_workflow_correlation(): void
    {
        Mail::fake();

        $company = $this->meinCompany();
        $billing = $this->meinUser('billing@sandbox-demo.com');
        $technician = $this->meinUser('technician@sandbox-demo.com');
        $routine = app(DemoRoutineFactory::class)->createForCompany($company->id, $technician);
        $correlationId = $routine->workflowInstance?->correlation_id;
        $this->assertNotNull($correlationId);

        $client = Client::query()->where('company_id', $company->id)->firstOrFail();

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'routine_id' => $routine->id,
            'client_id' => $client->id,
            'status' => InvoiceStatus::Issued,
            'number' => 'MEIN-DELIVER-001',
            'currency' => 'MXN',
            'tax_rate_snapshot' => 0.16,
            'subtotal' => 100,
            'tax_total' => 16,
            'total' => 116,
            'issued_at' => now(),
            'notify_client_on_issue' => false,
            'client_portal_visible' => false,
            'delivery_deferred' => true,
            'delivered_to_client_at' => null,
        ]);

        Sanctum::actingAs($billing);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/billing/invoices/{$invoice->id}/deliver", [
                'notify_client' => true,
                'client_portal_visible' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.client_portal_visible', true)
            ->assertJsonPath('data.delivery_deferred', false);

        $invoice->refresh();
        $this->assertNotNull($invoice->delivered_to_client_at);

        Mail::assertQueued(ClientInvoiceIssuedMail::class);

        $entry = AuditEntry::query()
            ->where('action', 'invoice.delivered_to_client')
            ->where('subject_id', $invoice->id)
            ->first();
        $this->assertNotNull($entry);
        $this->assertSame($correlationId, $entry->correlation_id);
    }
}
