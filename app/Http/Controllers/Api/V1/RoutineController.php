<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Routine;
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

    public function store(Request $request): JsonResponse
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
            'status' => \App\Enums\RoutineStatus::Assigned,
        ]);

        return response()->json(['data' => $routine->load(['asset', 'site', 'routineType'])], 201);
    }

    public function show(Routine $routine): JsonResponse
    {
        return response()->json([
            'data' => $routine->load([
                'asset',
                'site',
                'routineType.formVersion',
                'assignee',
                'executions',
                'latestExecution.consumptions.supplyItem',
                'generatedReports',
                'invoice.lines',
            ]),
        ]);
    }
}
