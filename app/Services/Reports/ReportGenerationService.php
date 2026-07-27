<?php

namespace App\Services\Reports;

use App\Jobs\GenerateRoutineReportJob;
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

    public function queueForRoutine(Routine $routine, RoutineExecution $execution): GeneratedReport
    {
        $routine->load(['routineType.reportTemplateVersion']);

        $templateVersion = $routine->routineType?->reportTemplateVersion;

        $report = GeneratedReport::query()->create([
            'company_id' => $routine->company_id,
            'routine_id' => $routine->id,
            'routine_execution_id' => $execution->id,
            'report_template_version_id' => $templateVersion?->id,
            'status' => 'queued',
            'disk' => config('noah.reports.disk', 'local'),
        ]);

        if (! config('noah.reports.async', true)) {
            $this->processQueuedReport($report->fresh());
        } else {
            GenerateRoutineReportJob::dispatch($report->id);
        }

        return $report->fresh();
    }

    public function processQueuedReport(GeneratedReport $report): GeneratedReport
    {
        $report->update(['status' => 'processing']);

        $routine = $report->routine()->with([
            'routineType.reportTemplateVersion',
            'routineType.formVersion.definition',
            'asset.catalogItem',
            'site',
            'company',
        ])->firstOrFail();

        $execution = $report->execution()->firstOrFail();
        $templateVersion = $routine->routineType?->reportTemplateVersion;

        try {
            $html = $this->htmlBuilder->build(
                $routine,
                $execution,
                $templateVersion?->components ?? [],
                $templateVersion?->page_settings ?? [],
                $templateVersion?->id,
            );

            $enablePhp = str_contains($html, 'type="text/php"');
            $pdf = Pdf::loadHTML($html)->setPaper('a4');
            $pdf->getDomPDF()->set_option('isPhpEnabled', $enablePhp);
            $pdf->getDomPDF()->set_option('isRemoteEnabled', false);

            $path = config('noah.reports.path_prefix').'/'.Str::uuid().'.pdf';
            $disk = Storage::disk($report->disk);
            $prefix = config('noah.reports.path_prefix');
            if (! $disk->exists($prefix)) {
                $disk->makeDirectory($prefix);
            }
            $contents = $pdf->output();
            $written = $disk->put($path, $contents);

            if ($written === false || ! $disk->exists($path)) {
                throw new \RuntimeException('No se pudo guardar el PDF en almacenamiento.');
            }

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

    /**
     * @deprecated Use queueForRoutine(); kept for direct invocation in tests if needed.
     */
    public function generateForRoutine(Routine $routine, RoutineExecution $execution): GeneratedReport
    {
        $report = $this->queueForRoutine($routine, $execution);

        if (config('queue.default') === 'sync') {
            return $this->processQueuedReport($report->fresh());
        }

        return $report;
    }
}
