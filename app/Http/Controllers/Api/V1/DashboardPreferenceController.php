<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DashboardPreference;
use App\Support\CurrentCompany;
use App\Support\DashboardWidgets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $companyId = (int) app(CurrentCompany::class)->id();

        $pref = DashboardPreference::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->first();

        if ($pref === null) {
            $pref = DashboardPreference::query()
                ->where('company_id', $companyId)
                ->whereNull('user_id')
                ->first();
        }

        return response()->json([
            'data' => [
                'layout' => $pref?->layout['widgets'] ?? DashboardWidgets::defaultLayout(),
                'catalog' => DashboardWidgets::catalog(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'widgets' => ['required', 'array', 'min:1'],
            'widgets.*' => ['string', 'max:64'],
        ]);

        $userId = $request->user()?->id;
        $companyId = (int) app(CurrentCompany::class)->id();

        $pref = DashboardPreference::query()->updateOrCreate(
            ['company_id' => $companyId, 'user_id' => $userId],
            ['layout' => ['widgets' => array_values($data['widgets'])]],
        );

        return response()->json(['data' => $pref->layout]);
    }
}
