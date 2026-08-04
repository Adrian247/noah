<?php

namespace Tests\Feature\Api;

use App\Jobs\GenerateRoutineReportJob;
use App\Models\Company;
use App\Models\Routine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesDemoRoutine;
use Tests\Support\VehicleDemoFormResponses;
use Tests\TestCase;

class AsyncReportQueueTest extends TestCase
{
    use CreatesDemoRoutine;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\Queue::clearResolvedInstances();
        parent::tearDown();
    }

    public function test_validate_dispatches_report_job_when_async_enabled(): void
    {
        config(['phoenix.reports.async' => true]);
        Queue::fake();
        $this->seed();
        $technician = User::query()->where('email', 'technician@sandbox-demo.com')->first();
        $supervisor = User::query()->where('email', 'supervisor@sandbox-demo.com')->first();
        $company = Company::query()->first();
        $routine = $this->demoRoutine($technician);

        $this->withToken($technician->createToken('t')->plainTextToken)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/routines/{$routine->id}/executions", [
                'technician_comments' => 'test async',
                'duration_minutes' => 30,
                'responses' => $this->premiumFormResponses(),
            ])
            ->assertCreated();

        Queue::fake();

        Sanctum::actingAs($supervisor);
        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/routines/{$routine->id}/validate")
            ->assertOk();

        Queue::assertPushed(GenerateRoutineReportJob::class);
    }
}
