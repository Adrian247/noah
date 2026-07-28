<?php

namespace Tests\Feature\Api;

use App\Enums\RoutineStatus;
use App\Models\Company;
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
        $technician = User::query()->where('email', 'tecnico@noah.local')->first();
        $supervisor = User::query()->where('email', 'supervisor@noah.local')->first();
        $company = Company::query()->first();
        $routine = app(DemoRoutineFactory::class)->createForCompany($company->id, $technician);

        $this->withToken($technician->createToken('t')->plainTextToken)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/routines/{$routine->id}/executions", [
                'technician_comments' => 'cambio de filtro y limpieza',
                'duration_minutes' => 120,
                'responses' => $this->premiumFormResponses(),
            ])
            ->assertCreated();

        Sanctum::actingAs($supervisor);
        $response = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/routines/{$routine->id}/validate");

        $response->assertOk();

        $routine->refresh();
        $this->assertSame(RoutineStatus::PendingBilling, $routine->status);
        $this->assertTrue(GeneratedReport::query()->where('routine_id', $routine->id)->exists());
        $this->assertTrue(Invoice::query()->where('routine_id', $routine->id)->where('status', 'draft')->exists());
    }
}
