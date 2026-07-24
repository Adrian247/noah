<?php

namespace App\Jobs;

use App\Models\GeneratedReport;
use App\Services\Reports\ReportGenerationService;
use App\Support\CurrentCompany;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateRoutineReportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $generatedReportId) {}

    public function handle(ReportGenerationService $reports): void
    {
        $report = GeneratedReport::query()
            ->with(['routine.company', 'execution'])
            ->findOrFail($this->generatedReportId);

        app()->instance(CurrentCompany::class, new CurrentCompany($report->routine->company));

        $reports->processQueuedReport($report);
    }
}
