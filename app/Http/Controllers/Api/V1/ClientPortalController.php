<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\GeneratedReport;
use App\Models\Invoice;
use App\Models\Routine;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\InvoiceDeliveryPackageBuilder;
use App\Support\AuditCorrelation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ClientPortalController extends Controller
{
    public function invoices(Request $request): JsonResponse
    {
        $clientId = $this->clientId($request);

        $items = Invoice::query()
            ->where('client_id', $clientId)
            ->where('status', InvoiceStatus::Issued)
            ->where('client_portal_visible', true)
            ->orderByDesc('issued_at')
            ->get(['id', 'number', 'custom_reference', 'status', 'total', 'currency', 'issued_at', 'routine_id']);

        return response()->json(['data' => $items]);
    }

    public function showInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        return response()->json([
            'data' => $invoice->load(['lines' => fn ($q) => $q->orderBy('sort_order'), 'routine.asset']),
        ]);
    }

    public function downloadInvoice(
        Request $request,
        Invoice $invoice,
        InvoiceDeliveryPackageBuilder $package,
        AuditLogger $audit,
    ): Response {
        $this->authorizeInvoice($request, $invoice);

        $audit->fromRequest($request, 'portal.invoice_downloaded', Invoice::class, $invoice->id);

        return $package->downloadResponse($invoice->load(['evidences.generatedReport']));
    }

    public function assets(Request $request): JsonResponse
    {
        $clientId = $this->clientId($request);

        $assignments = \App\Models\AssetClientAssignment::query()
            ->active()
            ->where('client_id', $clientId)
            ->with(['asset.catalogItem', 'asset.site'])
            ->orderByDesc('assigned_at')
            ->get();

        return response()->json(['data' => $assignments]);
    }

    public function routines(Request $request): JsonResponse
    {
        $clientId = $this->clientId($request);
        $assetIds = \App\Models\AssetClientAssignment::query()
            ->active()
            ->where('client_id', $clientId)
            ->pluck('asset_id');

        $routines = Routine::query()
            ->where(function ($q) use ($clientId, $assetIds) {
                $q->where('client_id', $clientId);
                if ($assetIds->isNotEmpty()) {
                    $q->orWhereIn('asset_id', $assetIds);
                }
            })
            ->with(['asset', 'client', 'routineType', 'latestExecution', 'invoice'])
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json($routines);
    }

    public function showRoutine(Request $request, Routine $routine): JsonResponse
    {
        $this->authorizeRoutine($request, $routine);

        return response()->json([
            'data' => $routine->load([
                'asset.catalogItem',
                'client',
                'routineType',
                'executions',
                'latestExecution',
                'generatedReports',
                'invoice' => fn ($q) => $q->where('client_portal_visible', true)->where('status', InvoiceStatus::Issued),
                'workflowInstance.transitions',
            ]),
        ]);
    }

    public function downloadRoutineReport(
        Request $request,
        Routine $routine,
        GeneratedReport $report,
        AuditLogger $audit,
    ): Response {
        $this->authorizeRoutine($request, $routine);

        if ((int) $report->routine_id !== (int) $routine->id) {
            abort(404);
        }

        if ($report->status !== 'ready' || $report->path === null) {
            abort(404, 'Informe no disponible.');
        }

        if (! Storage::disk($report->disk)->exists($report->path)) {
            abort(404, 'Archivo no encontrado.');
        }

        $audit->fromRequest($request, 'portal.report_downloaded', GeneratedReport::class, $report->id, [
            'routine_id' => $routine->id,
        ]);

        return Storage::disk($report->disk)->download(
            $report->path,
            'informe-rutina-'.$routine->id.'-'.$report->id.'.pdf',
            ['Cache-Control' => 'no-store, no-cache, must-revalidate'],
        );
    }

    private function clientId(Request $request): int
    {
        $membership = $request->attributes->get('membership');

        return (int) $membership->client_id;
    }

    private function authorizeInvoice(Request $request, Invoice $invoice): void
    {
        if ($invoice->client_id !== $this->clientId($request)) {
            abort(404);
        }
        if ($invoice->status !== InvoiceStatus::Issued || ! $invoice->client_portal_visible) {
            abort(404);
        }
    }

    private function authorizeRoutine(Request $request, Routine $routine): void
    {
        $clientId = $this->clientId($request);

        if ((int) $routine->client_id === $clientId) {
            return;
        }

        if ($routine->asset_id === null) {
            abort(404);
        }

        $allowed = \App\Models\AssetClientAssignment::query()
            ->active()
            ->where('client_id', $clientId)
            ->where('asset_id', $routine->asset_id)
            ->exists();

        if (! $allowed) {
            abort(404);
        }
    }
}
