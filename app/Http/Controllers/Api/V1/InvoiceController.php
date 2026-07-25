<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\InvoiceDraftEditor;
use App\Services\Identity\CompanyAuthorizationService;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly CompanyAuthorizationService $authorization,
        private readonly InvoiceDraftEditor $draftEditor,
    ) {}

    public function index(): JsonResponse
    {
        $invoices = Invoice::query()
            ->with(['lines', 'routine', 'client'])
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json($invoices);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json([
            'data' => $invoice->load(['lines' => fn ($q) => $q->orderBy('sort_order'), 'routine', 'client']),
        ]);
    }

    public function updateDraft(Request $request, Invoice $invoice, AuditLogger $audit): JsonResponse
    {
        $this->authorizePermission($request, 'billing.draft.edit');

        $invoice = $this->draftEditor->updateDraft($invoice, $request->all(), $request, $audit);

        return response()->json(['data' => $invoice]);
    }

    public function issue(Request $request, Invoice $invoice, AuditLogger $audit): JsonResponse
    {
        $this->authorizePermission($request, 'billing.issue');

        if ($invoice->status !== InvoiceStatus::Draft) {
            return response()->json(['message' => 'Only drafts can be issued.'], 422);
        }

        if ($invoice->client_id === null) {
            throw ValidationException::withMessages([
                'client_id' => ['Asigne un cliente en la prefactura antes de emitir.'],
            ]);
        }

        $invoice->update([
            'status' => InvoiceStatus::Issued,
            'number' => $invoice->number ?? 'INV-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT),
            'issued_at' => now(),
        ]);

        $invoice->routine?->update(['status' => \App\Enums\RoutineStatus::Invoiced]);

        $audit->fromRequest($request, 'invoice.issued', Invoice::class, $invoice->id, [
            'number' => $invoice->number,
        ]);

        return response()->json(['data' => $invoice->fresh(['lines', 'client'])]);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        $user = $request->user();
        $companyId = app(CurrentCompany::class)->id();

        if ($user === null || ! $this->authorization->userHasPermission($user, $companyId, $permission)) {
            abort(403, 'Insufficient permissions for billing action.');
        }
    }
}
