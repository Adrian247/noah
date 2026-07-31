<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WebhookSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookSubscriptionController extends Controller
{
    public function index(): JsonResponse
    {
        $items = WebhookSubscription::query()->orderBy('name')->get();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $subscription = WebhookSubscription::query()->create([
            ...$data,
            'secret' => Str::random(32),
        ]);

        return response()->json(['data' => $subscription->makeVisible(['secret'])], 201);
    }

    public function update(Request $request, WebhookSubscription $webhookSubscription): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'url', 'max:2048'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $webhookSubscription->update($data);

        return response()->json(['data' => $webhookSubscription->fresh()]);
    }

    public function destroy(WebhookSubscription $webhookSubscription): JsonResponse
    {
        $webhookSubscription->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
