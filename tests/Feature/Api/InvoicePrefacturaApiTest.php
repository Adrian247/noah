<?php

namespace Tests\Feature\Api;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Routine;
use App\Models\RoutineExecution;
use App\Models\User;
use App\Services\Billing\InvoiceDraftService;
use App\Support\CurrentCompany;
use App\Services\Identity\CompanyAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoicePrefacturaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(CompanyAuthorizationService::class)->bootstrapAllCompanies();
    }

    public function test_billing_can_edit_draft_lines(): void
    {
        $company = Company::query()->first();
        $billing = User::query()->where('email', 'facturacion@noah.local')->first();
        $routine = Routine::query()->first();
        $technician = User::query()->where('email', 'tecnico@noah.local')->first();

        $execution = $routine->executions()->create([
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

    public function test_issue_requires_client(): void
    {
        $company = Company::query()->first();
        $billing = User::query()->where('email', 'facturacion@noah.local')->first();
        $routine = Routine::query()->first();
        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'routine_id' => $routine->id,
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
}
