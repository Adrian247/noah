<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipRole;
use App\Enums\RoutineStatus;
use App\Http\Controllers\Controller;
use App\Models\Routine;
use App\Models\RoutineExecution;
use App\Services\AI\GrammarCorrectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoutineExecutionController extends Controller
{
    public function store(Request $request, Routine $routine, GrammarCorrectionService $grammar): JsonResponse
    {
        $data = $request->validate([
            'responses' => ['nullable', 'array'],
            'technician_comments' => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        $execution = $routine->executions()->create([
            'performed_by' => $request->user()->id,
            'responses' => $data['responses'] ?? [],
            'technician_comments' => $data['technician_comments'] ?? null,
            'corrected_comments' => isset($data['technician_comments'])
                ? $grammar->correct($data['technician_comments'])
                : null,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $routine->update(['status' => RoutineStatus::PendingValidation]);

        return response()->json(['data' => $execution->fresh()], 201);
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

        return response()->json(['data' => $routine->fresh(['latestExecution'])]);
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
        if (! in_array($membership->role, [MembershipRole::Administrator, MembershipRole::Supervisor], true)) {
            abort(403, 'Supervisor role required.');
        }
    }
}
