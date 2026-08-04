<?php

namespace Tests\Feature\Api;

use App\Enums\RoutineStatus;
use App\Models\Company;
use App\Models\Routine;
use App\Models\User;
use App\Services\Routines\DemoRoutineFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\VehicleDemoFormResponses;
use Tests\TestCase;

class WorkflowRuntimeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function demoResponses(): array
    {
        return VehicleDemoFormResponses::required();
    }

    public function test_execution_records_workflow_transition(): void
    {
        Mail::fake();
        $this->seed();
        $user = User::query()->where('email', 'technician@sandbox-demo.com')->first();
        $company = Company::query()->first();
        $routine = app(DemoRoutineFactory::class)->createForCompany($company->id, $user);
        $routine->load('workflowInstance');
        $this->assertNotNull($routine->workflowInstance);

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/routines/{$routine->id}/executions", [
                'technician_comments' => 'prueba workflow',
                'duration_minutes' => 30,
                'responses' => $this->demoResponses(),
            ])
            ->assertCreated();

        $this->assertDatabaseHas('workflow_transitions', [
            'workflow_instance_id' => $routine->workflowInstance->id,
            'trigger' => 'execution_submitted',
            'to_step' => 'supervisor_review',
        ]);
    }

    public function test_approval_moves_to_pending_billing(): void
    {
        Mail::fake();
        $this->seed();
        $technician = User::query()->where('email', 'technician@sandbox-demo.com')->first();
        $supervisor = User::query()->where('email', 'supervisor@sandbox-demo.com')->first();
        $company = Company::query()->first();
        $routine = app(DemoRoutineFactory::class)->createForCompany($company->id, $technician);
        $tokenT = $technician->createToken('t')->plainTextToken;

        $this->withToken($tokenT)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/routines/{$routine->id}/executions", [
                'technician_comments' => 'envío',
                'duration_minutes' => 30,
                'responses' => $this->demoResponses(),
            ])
            ->assertCreated();

        $tokenS = $supervisor->createToken('s')->plainTextToken;
        Sanctum::actingAs($supervisor);
        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/routines/{$routine->id}/validate")
            ->assertOk();

        $routine->refresh();
        $this->assertSame(RoutineStatus::PendingBilling, $routine->status);
        $this->assertSame('billing_review', $routine->workflowInstance?->current_step_key);
        $this->assertNotNull($routine->workflowInstance?->correlation_id);
    }
}
