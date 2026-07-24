<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CatalogItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogItemController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => CatalogItem::query()->orderBy('code')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'specifications' => ['nullable', 'array'],
        ]);

        $item = CatalogItem::query()->create($data);

        return response()->json(['data' => $item], 201);
    }

    public function update(Request $request, CatalogItem $catalogItem): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:64'],
            'name' => ['sometimes', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'specifications' => ['nullable', 'array'],
        ]);

        $catalogItem->update($data);

        return response()->json(['data' => $catalogItem->fresh()]);
    }

    public function destroy(CatalogItem $catalogItem): JsonResponse
    {
        if ($catalogItem->assets()->exists()) {
            return response()->json(['message' => 'Cannot delete: assets reference this catalog item.'], 422);
        }

        $catalogItem->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
