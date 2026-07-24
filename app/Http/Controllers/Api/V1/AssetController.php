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
}
