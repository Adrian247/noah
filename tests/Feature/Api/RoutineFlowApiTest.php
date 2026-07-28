<?php

namespace Tests\Feature\Api;

use App\Enums\RoutineStatus;
use App\Models\Company;
use App\Models\Routine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDemoRoutine;
use Tests\TestCase;

class RoutineFlowApiTest extends TestCase
{
    use CreatesDemoRoutine;
    use RefreshDatabase;

    public function test_submit_execution_moves_to_pending_validation(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'tecnico@noah.local')->first();
        $company = Company::query()->first();
        $routine = $this->demoRoutine($user);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/routines/{$routine->id}/executions", [
                'technician_comments' => 'se cambio filtro y limpieza general',
                'duration_minutes' => 90,
                'responses' => $this->premiumFormResponses(),
            ])
            ->assertCreated();

        $routine->refresh();
        $this->assertSame(RoutineStatus::PendingValidation, $routine->status);
        $this->assertNotNull($routine->latestExecution?->corrected_comments);
    }

    public function test_routine_show_includes_evidences_relation(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'tecnico@noah.local')->first();
        $company = Company::query()->first();
        $routine = $this->demoRoutine($user);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/routines/{$routine->id}/executions", [
                'technician_comments' => 'prueba',
                'duration_minutes' => 10,
                'responses' => $this->premiumFormResponses(),
            ])
            ->assertCreated();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson("/api/v1/routines/{$routine->id}")
            ->assertOk()
            ->assertJsonPath('data.latest_execution.evidences', []);
    }
}
