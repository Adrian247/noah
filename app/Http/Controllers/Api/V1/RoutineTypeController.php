<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RoutineType;
use Illuminate\Http\JsonResponse;

class RoutineTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => RoutineType::query()
                ->with(['formVersion', 'reportTemplateVersion', 'workflowDefinition'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }
}
