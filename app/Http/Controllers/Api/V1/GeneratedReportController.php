<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GeneratedReport;
use App\Services\Reports\ReportGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeneratedReportController extends Controller
{
    public function index(int $routineId): JsonResponse
    {
        $reports = GeneratedReport::query()
            ->where('routine_id', $routineId)
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $reports]);
    }

    public function download(GeneratedReport $report, ReportGenerationService $reports): StreamedResponse|JsonResponse
    {
        if (app()->environment('local') && $report->status === 'ready') {
            try {
                $reports->processQueuedReport($report->fresh());
                $report->refresh();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($report->status !== 'ready' || $report->path === null) {
            return response()->json(['message' => 'Report not ready.'], 404);
        }

        if (! Storage::disk($report->disk)->exists($report->path)) {
            return response()->json(['message' => 'File missing.'], 404);
        }

        return Storage::disk($report->disk)->download(
            $report->path,
            'reporte-rutina-'.$report->routine_id.'.pdf',
            ['Cache-Control' => 'no-store, no-cache, must-revalidate'],
        );
    }
}
