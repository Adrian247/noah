<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Models\Routine;
use App\Models\SupplyItem;
use App\Services\AI\AiGateway;
use App\Services\Audit\AuditLogger;
use App\Services\Workflow\WorkflowRuntime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoutineExecutionController extends Controller
{
    public function store(
        Request $request,
        Routine $routine,
        AiGateway $ai,
        WorkflowRuntime $workflow,
        AuditLogger $audit,
    ): JsonResponse {
        $data = $request->validate([
            'responses' => ['nullable', 'array'],
            'technician_comments' => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'consumptions' => ['nullable', 'array'],
            'consumptions.*.supply_item_id' => ['required', 'integer', 'exists:supply_items,id'],
            'consumptions.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'consumptions.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $corrected = null;
        if (! empty($data['technician_comments'])) {
            $corrected = $ai->correctGrammar($data['technician_comments'], $request->user()->id);
        }

        $execution = $routine->executions()->create([
            'performed_by' => $request->user()->id,
            'responses' => $data['responses'] ?? [],
            'technician_comments' => $data['technician_comments'] ?? null,
            'corrected_comments' => $corrected,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        foreach ($data['consumptions'] ?? [] as $line) {
            $supply = SupplyItem::query()->findOrFail($line['supply_item_id']);
            $execution->consumptions()->create([
                'supply_item_id' => $supply->id,
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'] ?? $supply->standard_cost ?? 0,
            ]);
        }

        $workflow->onExecutionSubmitted($routine, $request->user());

        $audit->fromRequest($request, 'routine.execution_submitted', Routine::class, $routine->id, [
            'execution_id' => $execution->id,
        ]);

        return response()->json(['data' => $execution->fresh(['consumptions.supplyItem'])], 201);
    }

    public function validateExecution(
        Request $request,
        Routine $routine,
        WorkflowRuntime $workflow,
        AuditLogger $audit,
    ): JsonResponse {
        $this->authorizeSupervisor($request);

        $execution = $routine->latestExecution;
        if ($execution === null) {
            return response()->json(['message' => 'No execution to validate.'], 422);
        }

        $workflow->onApproved($routine, $request->user());

        $audit->fromRequest($request, 'routine.validated', Routine::class, $routine->id);

        return response()->json([
            'data' => $routine->fresh([
                'latestExecution',
                'generatedReports',
                'invoice.lines',
                'workflowInstance.transitions',
            ]),
        ]);
    }

    public function reject(
        Request $request,
        Routine $routine,
        WorkflowRuntime $workflow,
        AuditLogger $audit,
    ): JsonResponse {
        $this->authorizeSupervisor($request);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $workflow->onRejected($routine, $request->user(), $validated['reason']);

        $execution = $routine->latestExecution;
        if ($execution !== null) {
            $execution->update([
                'rejection_reason' => $validated['reason'],
                'rejected_at' => now(),
                'rejected_by' => $request->user()->id,
            ]);
        }

        $audit->fromRequest($request, 'routine.rejected', Routine::class, $routine->id, [
            'reason' => $validated['reason'],
        ]);

        return response()->json(['data' => $routine->fresh(['workflowInstance.transitions'])]);
    }

    private function authorizeSupervisor(Request $request): void
    {
        $membership = $request->attributes->get('membership');
        $role = $membership->role;
        if ($role instanceof MembershipRole) {
            $roleValue = $role->value;
        } else {
            $roleValue = (string) $role;
        }

        if (! in_array($roleValue, [MembershipRole::Administrator->value, MembershipRole::Supervisor->value], true)) {
            abort(403, 'Supervisor role required.');
        }
    }
}
