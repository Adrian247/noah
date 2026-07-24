<?php

namespace App\Listeners;

use App\Events\RoutineValidated;
use App\Services\Reports\ReportGenerationService;
use App\Support\CurrentCompany;

class GenerateRoutineReport
{
    public function __construct(private ReportGenerationService $reports) {}

    public function handle(RoutineValidated $event): void
    {
        $routine = $event->routine->load(['company', 'routineType.reportTemplateVersion', 'asset', 'site']);
        app()->instance(CurrentCompany::class, new CurrentCompany($routine->company));

        try {
            $this->reports->generateForRoutine($routine, $event->execution);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
