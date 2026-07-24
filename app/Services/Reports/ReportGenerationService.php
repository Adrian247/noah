<?php

namespace App\Services\Reports;

use App\Models\GeneratedReport;
use App\Models\Routine;
use App\Models\RoutineExecution;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReportGenerationService
{
    public function __construct(
        private ReportHtmlBuilder $htmlBuilder,
    ) {}

    public function generateForRoutine(Routine $routine, RoutineExecution $execution): GeneratedReport
    {
        $routine->load(['routineType.reportTemplateVersion', 'asset.catalogItem', 'site', 'company']);
        $templateVersion = $routine->routineType?->reportTemplateVersion;

        $report = GeneratedReport::query()->create([
            'company_id' => $routine->company_id,
            'routine_id' => $routine->id,
            'routine_execution_id' => $execution->id,
            'report_template_version_id' => $templateVersion?->id,
            'status' => 'processing',
            'disk' => config('noah.reports.disk', 'local'),
        ]);

        try {
            $html = $this->htmlBuilder->build($routine, $execution, $templateVersion?->components ?? []);
            $pdf = Pdf::loadHTML($html)->setPaper('a4');

            $path = config('noah.reports.path_prefix').'/'.Str::uuid().'.pdf';
            Storage::disk($report->disk)->put($path, $pdf->output());

            $report->update([
                'status' => 'ready',
                'path' => $path,
                'mime' => 'application/pdf',
            ]);
        } catch (\Throwable $e) {
            $report->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $report->fresh();
    }
}
