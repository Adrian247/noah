<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Routine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WorkflowRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_execution_records_workflow_transition(): void
    {
        Mail::fake();
        $this->seed();
        $user = User::query()->where('email', 'tecnico@noah.local')->first();
        $company = Company::query()->first();
        $routine = Routine::query()->with('workflowInstance')->first();
        $this->assertNotNull($routine->workflowInstance);

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/routines/{$routine->id}/executions", [
                'technician_comments' => 'prueba workflow',
                'duration_minutes' => 30,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('workflow_transitions', [
            'workflow_instance_id' => $routine->workflowInstance->id,
            'trigger' => 'execution_submitted',
            'to_step' => 'supervisor_review',
        ]);
    }
}
