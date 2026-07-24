<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Models\RoutineType;
use App\Models\WorkflowDefinition;
use App\Services\Audit\AuditLogger;
use App\Services\Workflow\WorkflowRuntime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowDefinitionController extends Controller
{
    public function index(): JsonResponse
    {
        $items = WorkflowDefinition::query()
            ->orderBy('name')
            ->orderByDesc('version')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function show(WorkflowDefinition $workflowDefinition): JsonResponse
    {
        return response()->json([
            'data' => [
                ...$workflowDefinition->toArray(),
                'definition' => WorkflowRuntime::withDefaultLayout($workflowDefinition->definition ?? []),
            ],
        ]);
    }

    public function updateDefinition(
        Request $request,
        WorkflowDefinition $workflowDefinition,
        AuditLogger $audit,
    ): JsonResponse {
        $this->authorizeDesigner($request);

        $data = $request->validate([
            'definition' => ['required', 'array'],
            'definition.initial_step' => ['required', 'string'],
            'definition.steps' => ['required', 'array'],
            'definition.transitions' => ['required', 'array'],
            'definition.layout' => ['nullable', 'array'],
        ]);

        $workflowDefinition->update([
            'definition' => WorkflowRuntime::withDefaultLayout($data['definition']),
        ]);

        $audit->fromRequest($request, 'workflow.definition_updated', WorkflowDefinition::class, $workflowDefinition->id);

        return response()->json(['data' => $workflowDefinition->fresh()]);
    }

    public function updateRoutineTypeWorkflow(Request $request, RoutineType $routineType): JsonResponse
    {
        $this->authorizeDesigner($request);

        $data = $request->validate([
            'workflow_definition_id' => ['nullable', 'exists:workflow_definitions,id'],
        ]);

        $routineType->update($data);

        return response()->json(['data' => $routineType->fresh(['workflowDefinition', 'formVersion', 'reportTemplateVersion'])]);
    }

    private function authorizeDesigner(Request $request): void
    {
        $membership = $request->attributes->get('membership');
        $role = $membership->role;
        $roleValue = $role instanceof MembershipRole ? $role->value : (string) $role;

        if ($roleValue !== MembershipRole::Administrator->value) {
            abort(403, 'Administrator role required.');
        }
    }
}
