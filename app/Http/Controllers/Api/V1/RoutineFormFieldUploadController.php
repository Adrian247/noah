<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Routine;
use App\Services\Forms\FormDesignSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RoutineFormFieldUploadController extends Controller
{
    public function store(Request $request, Routine $routine, FormDesignSettings $settings): JsonResponse
    {
        $settingsData = $settings->forCurrentCompany();
        $maxKb = $settingsData['max_image_size_kb'];
        $mimes = implode(',', array_map(
            fn (string $m) => str_replace('image/', '', $m),
            $settingsData['allowed_image_mimes'],
        ));

        $validated = $request->validate([
            'field_key' => ['required', 'string', 'max:128'],
            'file' => ['required', 'file', 'max:'.$maxKb, 'mimes:'.$mimes],
        ]);

        $diskName = config('phoenix.evidence.disk', 'evidence');
        $file = $request->file('file');
        $path = config('phoenix.evidence.path_prefix').'/'.$routine->id.'/fields/'.Str::uuid().'.'.$file->getClientOriginalExtension();

        $disk = Storage::disk($diskName);
        $dir = dirname($path);
        if (! $disk->exists($dir)) {
            $disk->makeDirectory($dir);
        }

        $stored = $disk->put($path, file_get_contents($file->getRealPath()));
        if ($stored === false) {
            return response()->json(['message' => 'No se pudo guardar la imagen.'], 500);
        }

        return response()->json([
            'data' => [
                'field_key' => $validated['field_key'],
                'path' => $path,
                'disk' => $diskName,
                'mime' => $file->getMimeType(),
                'original_name' => $file->getClientOriginalName(),
            ],
        ], 201);
    }

    /**
     * Sirve una imagen de campo de formulario asociada al servicio (vista previa en SPA).
     */
    public function show(Request $request, Routine $routine): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:512'],
        ]);

        $path = str_replace('\\', '/', $validated['path']);
        if (str_contains($path, '..') || str_starts_with($path, '/')) {
            return response()->json(['message' => 'Ruta de imagen inválida.'], 422);
        }

        $prefix = trim((string) config('phoenix.evidence.path_prefix'), '/').'/'.$routine->id.'/';
        if (! str_starts_with($path, $prefix)) {
            return response()->json(['message' => 'La imagen no pertenece a este servicio.'], 403);
        }

        $diskName = config('phoenix.evidence.disk', 'evidence');
        $disk = Storage::disk($diskName);
        if (! $disk->exists($path)) {
            return response()->json(['message' => 'Archivo no encontrado.'], 404);
        }

        $mime = $disk->mimeType($path) ?: 'image/jpeg';

        return response()->stream(function () use ($disk, $path): void {
            echo $disk->get($path);
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
