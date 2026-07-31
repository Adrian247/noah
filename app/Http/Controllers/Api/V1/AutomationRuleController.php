<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AutomationRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomationRuleController extends Controller
{
    public function index(): JsonResponse
    {
        $items = AutomationRule::query()->orderBy('name')->get();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger_type' => ['required', 'string', 'max:64'],
            'conditions' => ['nullable', 'array'],
            'actions' => ['required', 'array', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $rule = AutomationRule::query()->create($data);

        return response()->json(['data' => $rule], 201);
    }

    public function update(Request $request, AutomationRule $automationRule): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'trigger_type' => ['sometimes', 'string', 'max:64'],
            'conditions' => ['nullable', 'array'],
            'actions' => ['sometimes', 'array', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $automationRule->update($data);

        return response()->json(['data' => $automationRule->fresh()]);
    }

    public function destroy(AutomationRule $automationRule): JsonResponse
    {
        $automationRule->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
