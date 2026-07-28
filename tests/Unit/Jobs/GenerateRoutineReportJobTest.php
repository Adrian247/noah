<?php

namespace Tests\Unit\Jobs;

use App\Jobs\GenerateRoutineReportJob;
use App\Models\Company;
use App\Models\GeneratedReport;
use App\Models\Routine;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesDemoRoutine;
use Tests\Support\VehicleDemoFormResponses;
use Tests\TestCase;

class GenerateRoutineReportJobTest extends TestCase
{
    use CreatesDemoRoutine;
    use RefreshDatabase;

    public function test_job_processes_report_even_when_stale_company_context_is_set(): void
    {
        Storage::fake('local');
        config(['noah.reports.disk' => 'local', 'noah.reports.async' => true]);

        $this->seed();

        $companies = Company::query()->orderBy('id')->get();
        $this->assertGreaterThanOrEqual(1, $companies->count());

        $technician = \App\Models\User::query()->where('email', 'tecnico@noah.local')->firstOrFail();
        $routine = $this->demoRoutine($technician);
        $execution = $routine->latestExecution ?? $routine->executions()->create([
            'performed_by' => $technician->id,
            'responses' => VehicleDemoFormResponses::required(),
            'technician_comments' => 'Prueba job PDF',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $report = GeneratedReport::query()->create([
            'company_id' => $routine->company_id,
            'routine_id' => $routine->id,
            'routine_execution_id' => $execution->id,
            'status' => 'queued',
            'disk' => 'local',
        ]);

        $otherCompany = $companies->firstWhere('id', '!=', $routine->company_id) ?? $companies->first();
        app()->instance(CurrentCompany::class, new CurrentCompany($otherCompany));

        $job = new GenerateRoutineReportJob($report->id);
        $job->handle(app(\App\Services\Reports\ReportGenerationService::class));

        $report->refresh();
        $this->assertSame('ready', $report->status);
        $this->assertNotNull($report->path);
        Storage::disk('local')->assertExists($report->path);
    }
}
