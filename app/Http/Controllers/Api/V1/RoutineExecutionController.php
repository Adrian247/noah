<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipRole;
use App\Enums\RoutineStatus;
use App\Events\RoutineValidated;
use App\Http\Controllers\Controller;
use App\Models\Routine;
use App\Models\SupplyItem;
use App\Services\AI\AiGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoutineExecutionController extends Controller
{
    public function store(Request $request, Routine $routine, AiGateway $ai): JsonResponse
    {
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

        $routine->update(['status' => RoutineStatus::PendingValidation]);

        return response()->json(['data' => $execution->fresh(['consumptions.supplyItem'])], 201);
    }

    public function validateExecution(Request $request, Routine $routine): JsonResponse
    {
        $this->authorizeSupervisor($request);

        $execution = $routine->latestExecution;
        if ($execution === null) {
            return response()->json(['message' => 'No execution to validate.'], 422);
        }

        $execution->update([
            'validated_at' => now(),
            'validated_by' => $request->user()->id,
        ]);

        $routine->update(['status' => RoutineStatus::Validated]);

        RoutineValidated::dispatch($routine->fresh(), $execution->fresh());

        return response()->json([
            'data' => $routine->fresh([
                'latestExecution',
                'generatedReports',
                'invoice.lines',
            ]),
        ]);
    }

    public function reject(Request $request, Routine $routine): JsonResponse
    {
        $this->authorizeSupervisor($request);

        $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $routine->update(['status' => RoutineStatus::Rejected]);

        return response()->json(['data' => $routine->fresh()]);
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
