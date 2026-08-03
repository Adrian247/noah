<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PredictiveAlgorithmVersion;
use App\Models\User;
use App\Services\Predictive\PredictiveAlgorithmVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PlatformPredictiveAlgorithmController extends Controller
{
    public function __construct(
        private readonly PredictiveAlgorithmVersionService $service,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->service->list()]);
    }

    public function train(Request $request): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var User $actor */
        $actor = $request->user();

        try {
            // La UI de plataforma siempre genera minor; patch/major van por artisan.
            $version = $this->service->train($actor, [
                'bump' => 'minor',
                'notes' => $data['notes'] ?? null,
            ]);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json(['data' => $version], 201);
    }

    public function publish(Request $request, PredictiveAlgorithmVersion $version): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $published = $this->service->publish($version, $actor);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json(['data' => $published]);
    }

    public function archive(Request $request, PredictiveAlgorithmVersion $version): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $archived = $this->service->archive($version, $actor);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json(['data' => $archived]);
    }
}
