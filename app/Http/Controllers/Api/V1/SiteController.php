<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Site::query()->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $site = Site::query()->create($data);

        return response()->json(['data' => $site], 201);
    }

    public function update(Request $request, Site $site): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $site->update($data);

        return response()->json(['data' => $site->fresh()]);
    }

    public function destroy(Site $site): JsonResponse
    {
        if ($site->assets()->exists()) {
            return response()->json(['message' => 'No se puede eliminar un sitio con activos.'], 422);
        }

        $site->delete();

        return response()->json(null, 204);
    }
}
