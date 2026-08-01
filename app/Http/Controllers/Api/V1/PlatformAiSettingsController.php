<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AI\AiPlatformSettingsService;
use App\Services\AI\AiProviderCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformAiSettingsController extends Controller
{
    public function show(AiPlatformSettingsService $settings): JsonResponse
    {
        return response()->json(['data' => $settings->get()]);
    }

    public function update(Request $request, AiPlatformSettingsService $settings): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'in:google,openai,local'],
            'openai_model' => ['nullable', 'string', 'max:120'],
            'google_model' => ['nullable', 'string', 'max:120'],
            'openai_use_default' => ['sometimes', 'boolean'],
            'google_use_default' => ['sometimes', 'boolean'],
        ]);

        try {
            $payload = $settings->update($data);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $payload]);
    }

    public function models(Request $request, AiProviderCatalogService $catalog): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'in:google,openai,local'],
        ]);

        return response()->json(['data' => $catalog->listModels($data['provider'])]);
    }

    public function validateProvider(Request $request, AiProviderCatalogService $catalog): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'in:google,openai,local'],
        ]);

        return response()->json(['data' => $catalog->validate($data['provider'])]);
    }
}
