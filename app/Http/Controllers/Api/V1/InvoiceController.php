<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvoiceEvidenceKind;
use App\Enums\InvoiceStatus;
use App\Enums\MembershipRole;
use App\Enums\RoutineStatus;
use App\Http\Controllers\Controller;
use App\Mail\ClientInvoiceIssuedMail;
use App\Models\GeneratedReport;
use App\Models\Invoice;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\FiscalIssuanceService;
use App\Services\Billing\InvoiceClientDeliveryService;
use App\Services\Billing\InvoiceDeliveryPackageBuilder;
use App\Services\Billing\InvoiceDraftEditor;
use App\Services\Billing\InvoiceEvidenceService;
use App\Services\Identity\CompanyAuthorizationService;
use App\Services\Workflow\WorkflowRuntime;
use App\Support\AuditCorrelation;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly CompanyAuthorizationService $authorization,
        private readonly InvoiceDraftEditor $draftEditor,
        private readonly WorkflowRuntime $workflow,
        private readonly InvoiceEvidenceService $invoiceEvidences,
        private readonly InvoiceDeliveryPackageBuilder $deliveryPackage,
        private readonly InvoiceClientDeliveryService $clientDelivery,
        private readonly FiscalIssuanceService $fiscalIssuance,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()
            ->with(['lines', 'routine', 'client'])
            ->orderByDesc('id');

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('custom_reference', 'like', '%'.$search.'%');
                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }
                $q->orWhere('number', 'like', '%'.$search.'%');
            });
        }

        $invoices = $query->paginate(15);

        return response()->json($invoices);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load([
            'lines' => fn ($q) => $q->orderBy('sort_order'),
            'routine.workflowInstance.definition',
            'client',
            'evidences.generatedReport',
        ]);

        $evidencePayload = $invoice->evidences
            ->sortBy(fn ($e) => [$e->kind->value, $e->id])
            ->values()
            ->map(fn ($e) => $this->invoiceEvidences->toApiArray($e))
            ->all();

        $issueLabel = 'Emitir factura';
        $instance = $invoice->routine?->workflowInstance;
        if ($instance !== null) {
            foreach ($this->workflow->availableActions($instance) as $action) {
                if ($action['trigger'] === WorkflowRuntime::TRIGGER_INVOICE_ISSUED) {
                    $issueLabel = $action['label'];
                    break;
                }
            }
        }

        $attachedReportIds = $invoice->evidences
            ->where('kind', InvoiceEvidenceKind::RoutineReport)
            ->pluck('generated_report_id')
            ->filter()
            ->all();

        $routineReportsAvailable = [];
        if ($invoice->routine_id !== null) {
            $routineReportsAvailable = GeneratedReport::query()
                ->where('routine_id', $invoice->routine_id)
                ->where('status', 'ready')
                ->whereNotNull('path')
                ->when($attachedReportIds !== [], fn ($q) => $q->whereNotIn('id', $attachedReportIds))
                ->orderByDesc('id')
                ->get(['id', 'routine_id', 'routine_execution_id', 'status', 'created_at'])
                ->map(fn (GeneratedReport $r) => [
                    'id' => $r->id,
                    'routine_id' => $r->routine_id,
                    'routine_execution_id' => $r->routine_execution_id,
                    'status' => $r->status,
                    'created_at' => $r->created_at?->toIso8601String(),
                    'label' => 'Reporte #'.$r->id.' · ejecución #'.$r->routine_execution_id,
                ])
                ->all();
        }

        return response()->json([
            'data' => $invoice,
            'evidences' => $evidencePayload,
            'routine_reports_available' => $routineReportsAvailable,
            'workflow_action_labels' => [
                'invoice_issued' => $issueLabel,
            ],
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

        $routine = $invoice->routine;
        $instance = $routine?->workflowInstance;
        $definition = $instance?->definition?->definition;
        $hasBillingStep = WorkflowRuntime::definitionHasBillingStep($definition);

        if ($routine !== null && $hasBillingStep) {
            if ($routine->status !== RoutineStatus::PendingBilling) {
                throw ValidationException::withMessages([
                    'routine' => ['La rutina debe estar en facturación pendiente antes de emitir.'],
                ]);
            }
            if ($instance !== null && $instance->current_step_key !== WorkflowRuntime::STEP_BILLING) {
                throw ValidationException::withMessages([
                    'workflow' => ['El workflow debe estar en el paso de facturación.'],
                ]);
            }
        } elseif ($routine !== null && ! in_array($routine->status, [
            RoutineStatus::Validated,
            RoutineStatus::PendingBilling,
        ], true)) {
            throw ValidationException::withMessages([
                'routine' => ['La rutina debe estar validada antes de emitir la factura.'],
            ]);
        }

        $delivery = $request->validate([
            'notify_client_on_issue' => ['sometimes', 'boolean'],
            'client_portal_visible' => ['sometimes', 'boolean'],
            'delivery_deferred' => ['sometimes', 'boolean'],
        ]);

        $notify = (bool) ($delivery['notify_client_on_issue'] ?? $invoice->notify_client_on_issue);
        $portalVisible = (bool) ($delivery['client_portal_visible'] ?? $invoice->client_portal_visible);
        $deferred = (bool) ($delivery['delivery_deferred'] ?? $invoice->delivery_deferred);

        if ($notify && empty($invoice->client?->billing_email)) {
            throw ValidationException::withMessages([
                'notify_client_on_issue' => ['El cliente no tiene email de facturación.'],
            ]);
        }

        $fiscal = $this->fiscalIssuance->stampBeforeIssue($invoice->fresh(['company', 'lines', 'client']));
        if (! $fiscal['ok']) {
            throw ValidationException::withMessages([
                'fiscal' => [$fiscal['error'] ?? 'No se pudo timbrar la factura.'],
            ]);
        }

        $invoice->refresh();
        if ($invoice->status === InvoiceStatus::FiscalError) {
            throw ValidationException::withMessages([
                'fiscal' => [$invoice->fiscal_error ?? 'Error fiscal.'],
            ]);
        }

        $invoice->update([
            'status' => InvoiceStatus::Issued,
            'number' => $invoice->number ?? 'INV-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT),
            'issued_at' => now(),
            'notify_client_on_issue' => $notify,
            'client_portal_visible' => $portalVisible,
            'delivery_deferred' => $deferred,
            'delivered_to_client_at' => ($portalVisible || $notify) && ! $deferred ? now() : null,
        ]);

        if ($routine !== null) {
            $this->clientDelivery->bindWorkflowAuditCorrelation($invoice);
            if ($instance !== null && $this->workflow->canApplyTrigger($instance, WorkflowRuntime::TRIGGER_INVOICE_ISSUED)) {
                $this->workflow->onInvoiceIssued($routine, $request->user(), $audit);
            } else {
                $routine->update(['status' => RoutineStatus::Invoiced]);
            }
        }

        $audit->fromRequest($request, 'invoice.issued', Invoice::class, $invoice->id, [
            'number' => $invoice->number,
            'notify_client_on_issue' => $notify,
            'client_portal_visible' => $portalVisible,
            'delivery_deferred' => $deferred,
        ]);

        app(\App\Services\Integrations\OperationalEventBridge::class)->invoiceIssued($invoice->fresh());

        $fresh = $invoice->fresh(['lines', 'client', 'company']);

        if ($notify && ! $deferred && $fresh->client?->billing_email) {
            Mail::to($fresh->client->billing_email)->queue(
                new ClientInvoiceIssuedMail($fresh->load(['lines', 'client', 'company', 'evidences'])),
            );
        }

        return response()->json(['data' => $fresh]);
    }

    public function deliverToClient(Request $request, Invoice $invoice, AuditLogger $audit): JsonResponse
    {
        $this->authorizePermission($request, 'billing.issue');

        if ($invoice->status !== InvoiceStatus::Issued) {
            return response()->json(['message' => 'Solo facturas emitidas pueden entregarse al cliente.'], 422);
        }

        if ($invoice->client_id === null) {
            throw ValidationException::withMessages([
                'client_id' => ['La factura no tiene cliente asignado.'],
            ]);
        }

        $data = $request->validate([
            'notify_client' => ['required', 'boolean'],
            'client_portal_visible' => ['required', 'boolean'],
        ]);

        $fresh = $this->clientDelivery->deliver($invoice, [
            'notify_client' => (bool) $data['notify_client'],
            'client_portal_visible' => (bool) $data['client_portal_visible'],
        ], $request, $audit);

        return response()->json(['data' => $fresh]);
    }

    public function downloadPackage(Request $request, Invoice $invoice): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorizePermission($request, 'billing.draft');

        if ($invoice->status !== InvoiceStatus::Issued) {
            abort(422, 'Solo facturas emitidas tienen paquete de entrega.');
        }

        return $this->deliveryPackage->downloadResponse($invoice->load(['evidences.generatedReport']));
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
