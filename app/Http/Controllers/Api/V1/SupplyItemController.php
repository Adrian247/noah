<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SupplyItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplyItemController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => SupplyItem::query()->orderBy('sku')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:32'],
            'standard_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $item = SupplyItem::query()->create($data);

        return response()->json(['data' => $item], 201);
    }
}
