<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Routine;
use App\Services\AI\AiCapabilitiesService;
use App\Services\Analytics\OperationalAnalyticsService;
use App\Services\Insights\InsightAssistantService;
use Illuminate\Http\JsonResponse;
use App\Support\CurrentCompany;
use Illuminate\Http\Request;

class InsightsController extends Controller
{
    public function assistant(Request $request, InsightAssistantService $assistant): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
        ]);

        $companyId = (int) app(CurrentCompany::class)->id();
        $result = $assistant->answer($companyId, $data['question'], $request->user()?->id);

        return response()->json(['data' => $result]);
    }

    public function routineNarrative(Routine $routine, Request $request, AiCapabilitiesService $ai): JsonResponse
    {
        $text = $ai->generateReportNarrative($routine, $request->user()?->id);

        return response()->json(['data' => ['narrative' => $text]]);
    }

    public function routineCostEstimate(Routine $routine, OperationalAnalyticsService $analytics): JsonResponse
    {
        return response()->json(['data' => $analytics->estimateRoutineCost($routine)]);
    }

    public function assetSupplySuggestions(Asset $asset, OperationalAnalyticsService $analytics): JsonResponse
    {
        return response()->json(['data' => $analytics->suggestSuppliesForAsset($asset)]);
    }

    public function ocr(Request $request, AiCapabilitiesService $ai): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $text = $ai->extractPlateText(
            (string) file_get_contents($request->file('file')->getRealPath()),
            $request->user()?->id,
        );

        return response()->json(['data' => ['text' => $text]]);
    }
}
