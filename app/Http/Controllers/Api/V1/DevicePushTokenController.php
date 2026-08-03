<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DevicePushToken;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DevicePushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string', 'max:64'],
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'app_version' => ['nullable', 'string', 'max:32'],
        ]);

        $user = $request->user();
        $companyId = app(CurrentCompany::class)->id();

        DevicePushToken::query()
            ->where('token', $validated['token'])
            ->where('user_id', '!=', $user->id)
            ->delete();

        $row = DevicePushToken::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => $validated['device_id'],
            ],
            [
                'company_id' => $companyId,
                'platform' => $validated['platform'],
                'token' => $validated['token'],
                'app_version' => $validated['app_version'] ?? null,
                'last_seen_at' => now(),
            ],
        );

        return response()->json([
            'data' => [
                'id' => $row->id,
                'device_id' => $row->device_id,
                'platform' => $row->platform,
                'registered' => true,
            ],
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['nullable', 'string', 'max:64'],
            'token' => ['nullable', 'string', 'max:512'],
        ]);

        if (empty($validated['device_id']) && empty($validated['token'])) {
            return response()->json(['message' => 'Indica device_id o token.'], 422);
        }

        $query = DevicePushToken::query()->where('user_id', $request->user()->id);
        if (! empty($validated['device_id'])) {
            $query->where('device_id', $validated['device_id']);
        }
        if (! empty($validated['token'])) {
            $query->where('token', $validated['token']);
        }
        $query->delete();

        return response()->json(null, 204);
    }
}
