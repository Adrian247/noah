<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvoiceStatus;
use App\Enums\MembershipRole;
use App\Enums\RoutineStatus;
use App\Http\Controllers\Controller;
use App\Models\CompanyMembership;
use App\Models\Routine;
use App\Services\Audit\AuditLogger;
use App\Services\Forms\FormDesignSettings;
use App\Services\Routines\DemoRoutineFactory;
use App\Services\Workflow\WorkflowRuntime;
use App\Support\AuditCorrelation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoutineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $routines = Routine::query()
            ->with(['asset', 'site', 'routineType', 'assignee', 'latestExecution'])
            ->when(
                $request->query('status'),
                fn ($q, $status) => $q->where('status', $status)
            )
            ->orderByDesc('id')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($routines);
    }

    public function store(Request $request, WorkflowRuntime $workflow, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'site_id' => ['required', 'exists:sites,id'],
            'asset_id' => ['required', 'exists:assets,id'],
            'routine_type_id' => ['required', 'exists:routine_types,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        $routine = Routine::query()->create([
            ...$data,
            'created_by' => $request->user()->id,
            'status' => \App\Enums\RoutineStatus::Assigned,
        ]);

        $workflow->ensureInstance($routine->load('routineType.workflowDefinition'));
        $routine->load(['asset', 'site', 'routineType', 'assignee', 'workflowInstance']);
        $this->auditRoutineCreated($request, $audit, $routine);

        return response()->json(['data' => $routine], 201);
    }

    public function storeDemo(Request $request, DemoRoutineFactory $factory, AuditLogger $audit): JsonResponse
    {
        $membership = $request->attributes->get('membership');
        $role = $membership->role;
        $roleValue = $role instanceof MembershipRole ? $role->value : (string) $role;
        if ($roleValue !== MembershipRole::Administrator->value) {
            abort(403, 'Administrator role required.');
        }

        $companyId = (int) app(\App\Support\CurrentCompany::class)->id();
        $technician = CompanyMembership::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('role', MembershipRole::Technician)
            ->with('user')
            ->orderBy('id')
            ->first()
            ?->user
            ?? $request->user();

        $routine = $factory->createForCompany($companyId, $technician);
        $routine->loadMissing(['asset', 'site', 'routineType', 'assignee', 'workflowInstance']);
        $this->auditRoutineCreated($request, $audit, $routine);

        return response()->json(['data' => $routine], 201);
    }

    public function show(Routine $routine, FormDesignSettings $formDesign, WorkflowRuntime $workflow): JsonResponse
    {
        $routine->load([
            'asset',
            'site',
            'routineType.formVersion',
            'assignee',
            'executions',
            'latestExecution.consumptions.supplyItem',
            'latestExecution.evidences',
            'generatedReports',
            'invoice.lines',
            'workflowInstance.transitions',
        ]);

        if ($routine->workflowInstance !== null) {
            $instance = $routine->workflowInstance;
            $instance->loadMissing('definition');
            $instance->setAttribute(
                'available_actions',
                $workflow->availableActions($instance),
            );
            $instance->unsetRelation('definition');
        }

        return response()->json([
            'data' => $routine,
            'form_design' => [
                'settings' => $formDesign->forCurrentCompany(),
                'option_catalogs' => $formDesign->optionCatalogsForCurrentCompany(),
            ],
        ]);
    }

    public function destroy(Request $request, Routine $routine, AuditLogger $audit): JsonResponse
    {
        $this->authorizeAdministrator($request);

        $routine->load(['asset', 'site', 'routineType', 'assignee', 'workflowInstance', 'invoice']);

        if ($routine->invoice !== null && $routine->invoice->status === InvoiceStatus::Issued) {
            return response()->json([
                'message' => 'No se puede eliminar: la rutina tiene una factura emitida.',
            ], 422);
        }

        $correlationId = $routine->workflowInstance?->correlation_id;
        if ($correlationId) {
            AuditCorrelation::set($correlationId);
        }

        $metadata = [
            'routine_id' => $routine->id,
            'site_id' => $routine->site_id,
            'site_name' => $routine->site?->name,
            'asset_id' => $routine->asset_id,
            'asset_tag' => $routine->asset?->tag,
            'routine_type_id' => $routine->routine_type_id,
            'routine_type_name' => $routine->routineType?->name,
            'status' => $routine->status instanceof RoutineStatus
                ? $routine->status->value
                : (string) $routine->status,
            'correlation_id' => $correlationId,
        ];

        $routineId = $routine->id;
        $routine->delete();

        $audit->fromRequest($request, 'routine.deleted', Routine::class, $routineId, $metadata);

        return response()->json(null, 204);
    }

    private function authorizeAdministrator(Request $request): void
    {
        $membership = $request->attributes->get('membership');
        $role = $membership->role;
        $roleValue = $role instanceof MembershipRole ? $role->value : (string) $role;

        if ($roleValue !== MembershipRole::Administrator->value) {
            abort(403, 'Administrator role required.');
        }
    }

    private function auditRoutineCreated(Request $request, AuditLogger $audit, Routine $routine): void
    {
        $correlationId = $routine->workflowInstance?->correlation_id;
        if ($correlationId) {
            AuditCorrelation::set($correlationId);
        }

        $audit->fromRequest($request, 'routine.created', Routine::class, $routine->id, [
            'routine_id' => $routine->id,
            'site_id' => $routine->site_id,
            'site_name' => $routine->site?->name,
            'asset_id' => $routine->asset_id,
            'asset_tag' => $routine->asset?->tag,
            'routine_type_id' => $routine->routine_type_id,
            'routine_type_name' => $routine->routineType?->name,
            'assigned_to' => $routine->assigned_to,
            'assignee_name' => $routine->assignee?->name,
            'is_demo' => (bool) $routine->is_demo,
            'scheduled_at' => $routine->scheduled_at?->toIso8601String(),
            'correlation_id' => $correlationId,
        ]);
    }
}
