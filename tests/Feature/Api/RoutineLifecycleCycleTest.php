<?php

namespace Tests\Feature\Api;

use App\Enums\InvoiceStatus;
use App\Enums\RoutineStatus;
use App\Mail\ClientInvoiceIssuedMail;
use App\Models\AuditEntry;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Identity\CompanyAuthorizationService;
use App\Services\Routines\DemoRoutineFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\VehicleDemoFormResponses;
use Tests\Support\UsesMeinCompany;
use Tests\TestCase;

/**
 * Regresión automatizada del checklist 032 (ciclo rutina → portal → auditoría).
 */
class RoutineLifecycleCycleTest extends TestCase
{
    use RefreshDatabase;
    use UsesMeinCompany;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(CompanyAuthorizationService::class)->bootstrapAllCompanies();
    }

    /**
     * @return array<string, mixed>
     */
    private function demoResponses(): array
    {
        return VehicleDemoFormResponses::required();
    }

    public function test_technician_submit_supervisor_reject_and_resubmit(): void
    {
        Mail::fake();

        $company = $this->meinCompany();
        $technician = $this->meinUser('misael.palos@mein-company.com');
        $supervisor = $this->meinUser('claudio.rodriguez@mein-company.com');
        $routine = app(DemoRoutineFactory::class)->createForCompany($company->id, $technician);

        $this->withToken($technician->createToken('tech')->plainTextToken)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/routines/{$routine->id}/executions", [
                'technician_comments' => 'Primera ejecución demo',
                'duration_minutes' => 45,
                'responses' => $this->demoResponses(),
            ])
            ->assertCreated();

        Sanctum::actingAs($supervisor);
        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/routines/{$routine->id}/reject", [
                'reason' => 'Faltan fotos de frenos',
            ])
            ->assertOk();

        $routine->refresh();
        $this->assertSame(RoutineStatus::Assigned, $routine->status);
        $this->assertSame('field_execution', $routine->workflowInstance?->current_step_key);

        Sanctum::actingAs($technician);
        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/routines/{$routine->id}/executions", [
                'technician_comments' => 'Corrección con evidencias',
                'duration_minutes' => 30,
                'responses' => $this->demoResponses(),
            ])
            ->assertCreated();

        Sanctum::actingAs($supervisor);
        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/routines/{$routine->id}/validate")
            ->assertOk();

        $routine->refresh();
        $this->assertSame(RoutineStatus::PendingBilling, $routine->status);
        $this->assertTrue(Invoice::query()->where('routine_id', $routine->id)->where('status', 'draft')->exists());
    }

    public function test_issue_with_portal_visible_allows_client_download(): void
    {
        Mail::fake();

        $company = $this->meinCompany();
        $technician = $this->meinUser('misael.palos@mein-company.com');
        $supervisor = $this->meinUser('claudio.rodriguez@mein-company.com');
        $billing = $this->meinUser('elena.sanchez@mein-company.com');
        $portalUser = $this->meinUser('cliente.portal@mein-company.com');
        $routine = app(DemoRoutineFactory::class)->createForCompany($company->id, $technician);

        $this->submitAndValidate($routine->id, $company, $technician, $supervisor);

        $invoice = Invoice::query()->where('routine_id', $routine->id)->firstOrFail();
        Sanctum::actingAs($billing);
        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/billing/invoices/{$invoice->id}/issue", [
                'client_portal_visible' => true,
                'notify_client_on_issue' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.client_portal_visible', true);

        Sanctum::actingAs($portalUser);
        $this->withHeader('X-Company-Id', (string) $company->id)
            ->get("/api/v1/portal/invoices/{$invoice->id}/download")
            ->assertOk();

        Mail::assertNotQueued(ClientInvoiceIssuedMail::class);
    }

    public function test_issue_with_notify_email_queues_client_mail(): void
    {
        Mail::fake();

        $company = $this->meinCompany();
        $technician = $this->meinUser('misael.palos@mein-company.com');
        $supervisor = $this->meinUser('claudio.rodriguez@mein-company.com');
        $billing = $this->meinUser('elena.sanchez@mein-company.com');
        $routine = app(DemoRoutineFactory::class)->createForCompany($company->id, $technician);

        $client = Client::query()->where('company_id', $company->id)->firstOrFail();
        $client->update(['billing_email' => 'cliente-factura@example.test']);

        $this->submitAndValidate($routine->id, $company, $technician, $supervisor);

        $invoice = Invoice::query()->where('routine_id', $routine->id)->firstOrFail();
        Sanctum::actingAs($billing);
        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/billing/invoices/{$invoice->id}/issue", [
                'client_portal_visible' => false,
                'notify_client_on_issue' => true,
            ])
            ->assertOk();

        Mail::assertQueued(ClientInvoiceIssuedMail::class);
    }

    public function test_audit_correlation_groups_lifecycle_events(): void
    {
        Mail::fake();

        $company = $this->meinCompany();
        $technician = $this->meinUser('misael.palos@mein-company.com');
        $supervisor = $this->meinUser('claudio.rodriguez@mein-company.com');
        $billing = $this->meinUser('elena.sanchez@mein-company.com');
        $routine = app(DemoRoutineFactory::class)->createForCompany($company->id, $technician);
        $correlationId = $routine->workflowInstance?->correlation_id;
        $this->assertNotNull($correlationId);

        $this->submitAndValidate($routine->id, $company, $technician, $supervisor);

        $invoice = Invoice::query()->where('routine_id', $routine->id)->firstOrFail();
        Sanctum::actingAs($billing);
        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/billing/invoices/{$invoice->id}/issue", [
                'client_portal_visible' => true,
                'notify_client_on_issue' => false,
            ])
            ->assertOk();

        $count = AuditEntry::query()
            ->where('company_id', $company->id)
            ->where('correlation_id', $correlationId)
            ->count();

        $this->assertGreaterThanOrEqual(4, $count);
    }

    private function submitAndValidate(int $routineId, Company $company, User $technician, User $supervisor): void
    {
        $this->withToken($technician->createToken('tech')->plainTextToken)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/routines/{$routineId}/executions", [
                'technician_comments' => 'Ejecución para facturación',
                'duration_minutes' => 60,
                'responses' => $this->demoResponses(),
            ])
            ->assertCreated();

        Sanctum::actingAs($supervisor);
        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/routines/{$routineId}/validate")
            ->assertOk();
    }
}
