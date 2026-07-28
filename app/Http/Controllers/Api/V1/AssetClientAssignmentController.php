<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetClientAssignment;
use App\Models\Client;
use App\Services\Audit\AuditLogger;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AssetClientAssignmentController extends Controller
{
    public function index(Asset $asset): JsonResponse
    {
        $rows = AssetClientAssignment::query()
            ->where('asset_id', $asset->id)
            ->with(['client', 'assignedBy'])
            ->orderByDesc('assigned_at')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request, Asset $asset, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'serial_number' => ['required', 'string', 'max:128'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $companyId = app(CurrentCompany::class)->id();
        $client = Client::query()->where('company_id', $companyId)->findOrFail($data['client_id']);

        $normalizedAssetSerial = $this->normalizeSerial($asset->serial_number ?? '');
        $normalizedInput = $this->normalizeSerial($data['serial_number']);

        if ($normalizedAssetSerial === '' || $normalizedInput !== $normalizedAssetSerial) {
            throw ValidationException::withMessages([
                'serial_number' => ['El número de serie no coincide con el registrado en el activo.'],
            ]);
        }

        AssetClientAssignment::query()
            ->where('asset_id', $asset->id)
            ->active()
            ->update(['unassigned_at' => now()]);

        $row = AssetClientAssignment::query()->create([
            'asset_id' => $asset->id,
            'client_id' => $client->id,
            'serial_number' => $data['serial_number'],
            'assigned_by_user_id' => $request->user()?->id,
            'notes' => $data['notes'] ?? null,
            'assigned_at' => now(),
        ]);

        $audit->fromRequest($request, 'asset.client_linked', Asset::class, $asset->id, [
            'client_id' => $client->id,
            'assignment_id' => $row->id,
        ]);

        return response()->json(['data' => $row->load('client')], 201);
    }

    private function normalizeSerial(string $value): string
    {
        return strtoupper(trim($value));
    }
}
