<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;

class SiteController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Site::query()->orderBy('name')->get()]);
    }
}
