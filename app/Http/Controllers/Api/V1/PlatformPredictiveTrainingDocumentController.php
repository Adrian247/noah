<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PredictiveTrainingDocument;
use App\Models\User;
use App\Services\Predictive\PredictiveTrainingDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlatformPredictiveTrainingDocumentController extends Controller
{
    public function __construct(
        private readonly PredictiveTrainingDocumentService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->list($request->query('kind')),
        ]);
    }

    public function schemas(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->schemas(),
            'guide' => $this->service->guide(),
        ]);
    }

    public function template(Request $request, string $kind): StreamedResponse
    {
        $format = (string) $request->query('format', 'json');

        try {
            $template = $this->service->template($kind, $format);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->streamDownload(
            static function () use ($template): void {
                echo $template['contents'];
            },
            $template['filename'],
            [
                'Content-Type' => $template['mime'],
            ],
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'string', 'max:64'],
            'name' => ['nullable', 'string', 'max:160'],
            'file' => ['required', 'file', 'max:10240', 'extensions:json,csv,txt'],
        ]);

        /** @var User $actor */
        $actor = $request->user();

        try {
            $doc = $this->service->upload(
                $actor,
                $request->file('file'),
                $data['kind'],
                $data['name'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        $status = ($doc['status'] ?? '') === PredictiveTrainingDocument::STATUS_INVALID ? 422 : 201;

        return response()->json(['data' => $doc], $status);
    }

    public function destroy(Request $request, PredictiveTrainingDocument $document): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->service->destroy($document, $actor);

        return response()->json(['message' => 'Documento eliminado.']);
    }
}
