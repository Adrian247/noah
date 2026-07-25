<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClientController extends Controller
{
    public function index(): JsonResponse
    {
        $clients = Client::query()
            ->orderBy('legal_name')
            ->get();

        return response()->json([
            'data' => $clients->map(fn (Client $client) => self::formatClient($client)),
        ]);
    }

    public function show(Client $client): JsonResponse
    {
        return response()->json(['data' => self::formatClient($client)]);
    }

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $this->validated($request);

        $client = Client::query()->create($data);

        $audit->fromRequest($request, 'client.created', Client::class, $client->id);

        return response()->json(['data' => self::formatClient($client)], 201);
    }

    public function update(Request $request, Client $client, AuditLogger $audit): JsonResponse
    {
        $data = $this->validated($request, true);

        $client->update($data);

        $audit->fromRequest($request, 'client.updated', Client::class, $client->id);

        return response()->json(['data' => self::formatClient($client->fresh())]);
    }

    public function updateLogo(Request $request, Client $client, AuditLogger $audit): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ]);

        if ($client->logo_path && Storage::disk('public')->exists($client->logo_path)) {
            Storage::disk('public')->delete($client->logo_path);
        }

        $path = $request->file('logo')->store('clients/'.$client->id, 'public');
        $client->update(['logo_path' => $path]);

        $audit->fromRequest($request, 'client.logo_updated', Client::class, $client->id);

        return response()->json(['data' => self::formatClient($client->fresh())]);
    }

    public function destroy(Request $request, Client $client, AuditLogger $audit): JsonResponse
    {
        $client->update(['is_active' => false]);

        $audit->fromRequest($request, 'client.deactivated', Client::class, $client->id);

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial = false): array
    {
        $rules = [
            'code' => ['nullable', 'string', 'max:64'],
            'legal_name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:64'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_address' => ['nullable', 'string', 'max:2000'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        return $request->validate($rules);
    }

    /**
     * @return array<string, mixed>
     */
    public static function formatClient(Client $client): array
    {
        return [
            'id' => $client->id,
            'code' => $client->code,
            'legal_name' => $client->legal_name,
            'trade_name' => $client->trade_name,
            'logo_url' => self::logoUrl($client),
            'tax_id' => $client->tax_id,
            'billing_email' => $client->billing_email,
            'billing_address' => $client->billing_address,
            'currency' => $client->currency,
            'is_active' => $client->is_active,
            'notes' => $client->notes,
        ];
    }

    public static function logoUrl(Client $client): ?string
    {
        if ($client->logo_path === null || $client->logo_path === '') {
            return null;
        }

        if (! Storage::disk('public')->exists($client->logo_path)) {
            return null;
        }

        return Storage::disk('public')->url($client->logo_path);
    }
}
