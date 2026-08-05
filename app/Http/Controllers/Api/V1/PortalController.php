<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PortalSetting;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => PortalSetting::current()->toPortalPayload(),
        ]);
    }

    public function update(Request $request, AuditLogger $audit): JsonResponse
    {
        $validated = $request->validate([
            'service_title' => ['nullable', 'string', 'max:255'],
            'service_description' => ['nullable', 'string', 'max:5000'],
            'service_highlights' => ['nullable', 'array'],
            'service_highlights.*' => ['string', 'max:255'],
            'help_title' => ['nullable', 'string', 'max:255'],
            'help_text' => ['nullable', 'string', 'max:5000'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:64'],
            'contact_hours' => ['nullable', 'string', 'max:255'],
        ]);

        $portal = PortalSetting::current();
        $portal->update($validated);

        $audit->fromRequest($request, 'portal.updated', PortalSetting::class, $portal->id);

        return response()->json([
            'data' => $portal->fresh()->toPortalPayload(),
        ]);
    }
}
