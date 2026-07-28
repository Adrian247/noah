<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Models\Routine;
use App\Services\Routines\DemoRoutineFactory;
use App\Services\Workflow\WorkflowRuntime;
use App\Services\Forms\FormDesignSettings;
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

    public function store(Request $request, WorkflowRuntime $workflow): JsonResponse
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

        return response()->json(['data' => $routine->load(['asset', 'site', 'routineType', 'workflowInstance'])], 201);
    }

    public function storeDemo(Request $request, DemoRoutineFactory $factory): JsonResponse
    {
        $membership = $request->attributes->get('membership');
        $role = $membership->role;
        $roleValue = $role instanceof MembershipRole ? $role->value : (string) $role;
        if ($roleValue !== MembershipRole::Administrator->value) {
            abort(403, 'Administrator role required.');
        }

        $technician = \App\Models\User::query()->where('email', 'tecnico@noah.local')->first()
            ?? $request->user();

        $routine = $factory->createForCompany((int) app(\App\Support\CurrentCompany::class)->id(), $technician);

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
}
