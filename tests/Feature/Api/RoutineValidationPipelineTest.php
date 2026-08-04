<?php

namespace Tests\Feature\Api;

use App\Enums\RoutineStatus;
use App\Models\GeneratedReport;
use App\Models\Invoice;
use App\Services\Routines\DemoRoutineFactory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesDemoRoutine;
use Tests\TestCase;

class RoutineValidationPipelineTest extends TestCase
{
    use CreatesDemoRoutine;
    use RefreshDatabase;

    public function test_validate_creates_report_and_invoice_draft(): void
    {
        $this->seed();
        $technician = User::query()->where('email', 'technician@sandbox-demo.com')->firstOrFail();
        $supervisor = User::query()->where('email', 'supervisor@sandbox-demo.com')->firstOrFail();
        $companyId = (int) $technician->memberships()
            ->where('is_active', true)
            ->orderBy('company_id')
            ->value('company_id');
        $this->assertGreaterThan(0, $companyId);

        $routine = app(DemoRoutineFactory::class)->createForCompany($companyId, $technician);

        $this->withToken($technician->createToken('t')->plainTextToken)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/routines/{$routine->id}/executions", [
                'technician_comments' => 'cambio de filtro y limpieza',
                'duration_minutes' => 120,
                'responses' => $this->premiumFormResponses(),
            ])
            ->assertCreated();

        Sanctum::actingAs($supervisor);
        $response = $this->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/routines/{$routine->id}/validate");

        $response->assertOk();

        $routine->refresh();
        $this->assertSame(RoutineStatus::PendingBilling, $routine->status);
        $this->assertSame(1, GeneratedReport::query()->where('routine_id', $routine->id)->count());
        $this->assertSame(1, Invoice::query()->where('routine_id', $routine->id)->where('status', 'draft')->count());
    }
}
