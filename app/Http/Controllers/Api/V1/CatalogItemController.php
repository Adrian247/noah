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
}
