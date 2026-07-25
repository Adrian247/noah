<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\MobileSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function sync(Request $request, MobileSyncService $sync): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:128'],
            'events' => ['nullable', 'array'],
            'events.*.event_id' => ['required', 'string', 'max:64'],
            'events.*.event_type' => ['required', 'string', 'max:64'],
            'events.*.payload' => ['nullable', 'array'],
            'pull' => ['nullable', 'boolean'],
        ]);

        $result = ['push' => null, 'pull' => null];

        if (! empty($data['events'])) {
            $result['push'] = $sync->pushEvents(
                $request->user(),
                $data['device_id'],
                $data['events'],
            );
        }

        if ($data['pull'] ?? true) {
            $result['pull'] = $sync->pullForUser($request->user());
        }

        return response()->json(['data' => $result]);
    }
}
