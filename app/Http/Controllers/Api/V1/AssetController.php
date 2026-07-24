<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Asset::query()->with(['site', 'catalogItem'])->orderBy('tag')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'site_id' => ['required', 'exists:sites,id'],
            'catalog_item_id' => ['nullable', 'exists:catalog_items,id'],
            'tag' => ['required', 'string', 'max:64'],
            'serial_number' => ['nullable', 'string', 'max:128'],
            'location_label' => ['nullable', 'string', 'max:128'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);

        $asset = Asset::query()->create($data);

        return response()->json(['data' => $asset->load(['site', 'catalogItem'])], 201);
    }

    public function update(Request $request, Asset $asset): JsonResponse
    {
        $data = $request->validate([
            'site_id' => ['sometimes', 'exists:sites,id'],
            'catalog_item_id' => ['nullable', 'exists:catalog_items,id'],
            'tag' => ['sometimes', 'string', 'max:64'],
            'serial_number' => ['nullable', 'string', 'max:128'],
            'location_label' => ['nullable', 'string', 'max:128'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);

        $asset->update($data);

        return response()->json(['data' => $asset->fresh(['site', 'catalogItem'])]);
    }

    public function destroy(Asset $asset): JsonResponse
    {
        if ($asset->routines()->exists()) {
            return response()->json(['message' => 'Cannot delete: routines reference this asset.'], 422);
        }

        $asset->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
