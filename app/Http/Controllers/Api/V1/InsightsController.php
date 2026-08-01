<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Routine;
use App\Services\AI\AiCapabilitiesService;
use App\Services\Analytics\OperationalAnalyticsService;
use App\Services\Insights\InsightAssistantService;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class InsightsController extends Controller
{
    public function assistant(Request $request, InsightAssistantService $assistant): JsonResponse
    {
        $this->assertCompanyAiEnabled();

        $data = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
            'context' => ['nullable', 'string', 'max:500'],
            'conversation_id' => ['nullable', 'string', 'uuid', 'max:64'],
            'history' => ['nullable', 'array', 'max:24'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.text' => ['required_with:history', 'string', 'max:2000'],
        ]);

        $companyId = (int) app(CurrentCompany::class)->id();
        $result = $assistant->answer(
            $companyId,
            $data['question'],
            $request->user()?->id,
            $data['context'] ?? null,
            $data['conversation_id'] ?? null,
            $data['history'] ?? [],
        );

        return response()->json(['data' => $result]);
    }

    public function routineNarrative(Routine $routine, Request $request, AiCapabilitiesService $ai): JsonResponse
    {
        $this->assertCompanyAiEnabled();
        $text = $ai->generateReportNarrative($routine, $request->user()?->id);

        return response()->json(['data' => ['narrative' => $text]]);
    }

    public function routineCostEstimate(Routine $routine, OperationalAnalyticsService $analytics): JsonResponse
    {
        $this->assertCompanyAiEnabled();

        return response()->json(['data' => $analytics->estimateRoutineCost($routine)]);
    }

    public function assetSupplySuggestions(Asset $asset, OperationalAnalyticsService $analytics): JsonResponse
    {
        $this->assertCompanyAiEnabled();

        return response()->json(['data' => $analytics->suggestSuppliesForAsset($asset)]);
    }

    public function ocr(Request $request, AiCapabilitiesService $ai): JsonResponse
    {
        $this->assertCompanyAiEnabled();

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
        ]);

        try {
            $text = $ai->extractPlateText(
                (string) file_get_contents($request->file('file')->getRealPath()),
                $request->user()?->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['text' => $text]]);
    }

    private function assertCompanyAiEnabled(): void
    {
        $company = app(CurrentCompany::class)->company;
        abort_if($company === null, 400, 'Company context required.');
        if (! $company->ai_enabled) {
            abort(403, 'La IA está deshabilitada para esta empresa.');
        }
    }
}
