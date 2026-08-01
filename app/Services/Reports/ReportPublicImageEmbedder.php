<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\Storage;

/**
 * Embebe imágenes del disco public como data-URI aptas para DomPDF.
 */
final class ReportPublicImageEmbedder
{
    public static function imgTag(string $path, string $class): string
    {
        if ($path === '') {
            return '';
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return '';
        }

        $mime = $disk->mimeType($path) ?: 'image/jpeg';
        $binary = $disk->get($path);
        $src = ReportPdfImageEncoder::toEmbeddedSrc($binary, $mime);
        if ($src === null) {
            return '';
        }

        return '<img class="'.e($class).'" src="'.$src.'" alt="" />';
    }
}
