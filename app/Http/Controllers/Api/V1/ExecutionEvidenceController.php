<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ExecutionEvidence;
use App\Models\Routine;
use App\Models\RoutineExecution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExecutionEvidenceController extends Controller
{
    public function store(Request $request, Routine $routine): JsonResponse
    {
        $execution = $routine->latestExecution;
        if ($execution === null) {
            return response()->json(['message' => 'No execution found for this routine.'], 422);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $diskName = config('noah.evidence.disk', 'evidence');
        $file = $request->file('file');
        $path = config('noah.evidence.path_prefix').'/'.$routine->id.'/'.Str::uuid().'.'.$file->getClientOriginalExtension();

        $disk = Storage::disk($diskName);
        $prefix = dirname($path);
        if ($prefix !== '.' && ! $disk->exists($prefix)) {
            $disk->makeDirectory($prefix);
        }

        $stored = $disk->put($path, file_get_contents($file->getRealPath()));
        if ($stored === false || ! $disk->exists($path)) {
            return response()->json(['message' => 'Could not store evidence file.'], 500);
        }

        $evidence = ExecutionEvidence::query()->create([
            'company_id' => $routine->company_id,
            'routine_execution_id' => $execution->id,
            'disk' => $diskName,
            'path' => $path,
            'mime' => $file->getMimeType(),
            'original_name' => $file->getClientOriginalName(),
            'size_bytes' => $file->getSize(),
        ]);

        return response()->json(['data' => $evidence], 201);
    }

    public function download(ExecutionEvidence $evidence): StreamedResponse|JsonResponse
    {
        if (! Storage::disk($evidence->disk)->exists($evidence->path)) {
            return response()->json(['message' => 'File missing.'], 404);
        }

        return Storage::disk($evidence->disk)->download(
            $evidence->path,
            $evidence->original_name ?? 'evidence.jpg',
        );
    }
}
