<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientSiteController extends Controller
{
    public function index(Client $client): JsonResponse
    {
        return response()->json([
            'data' => Site::query()
                ->where('client_id', $client->id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request, Client $client): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $site = Site::query()->create([
            ...$data,
            'client_id' => $client->id,
        ]);

        return response()->json(['data' => $site], 201);
    }

    public function update(Request $request, Client $client, Site $site): JsonResponse
    {
        if ($site->client_id !== $client->id) {
            abort(404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $site->update($data);

        return response()->json(['data' => $site->fresh()]);
    }

    public function destroy(Client $client, Site $site): JsonResponse
    {
        if ($site->client_id !== $client->id) {
            abort(404);
        }

        if ($site->assets()->exists()) {
            return response()->json(['message' => 'No se puede eliminar un sitio con artículos en inventario.'], 422);
        }

        $site->delete();

        return response()->json(null, 204);
    }
}
