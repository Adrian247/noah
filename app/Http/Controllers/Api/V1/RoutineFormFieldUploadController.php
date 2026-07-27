<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Routine;
use App\Services\Forms\FormDesignSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        $diskName = config('noah.evidence.disk', 'evidence');
        $file = $request->file('file');
        $path = config('noah.evidence.path_prefix').'/'.$routine->id.'/fields/'.Str::uuid().'.'.$file->getClientOriginalExtension();

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
}
