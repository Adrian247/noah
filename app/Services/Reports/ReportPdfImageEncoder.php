<?php

namespace App\Services\Reports;

/**
 * DomPDF (vía GD) no siempre acepta WebP en data-URI; normaliza a JPEG para el PDF.
 */
final class ReportPdfImageEncoder
{
    public static function toEmbeddedSrc(string $binary, string $mime): ?string
    {
        $mime = strtolower(trim($mime));
        if ($mime === '' || $mime === 'application/octet-stream') {
            $mime = self::guessMime($binary) ?? 'image/jpeg';
        }

        if ($mime === 'image/webp' || self::looksLikeWebp($binary)) {
            $jpeg = self::encodeAsJpeg($binary, 'webp');

            return $jpeg !== null ? 'data:image/jpeg;base64,'.base64_encode($jpeg) : null;
        }

        if ($mime === 'image/png') {
            $jpeg = self::encodeAsJpeg($binary, 'png');
            if ($jpeg !== null) {
                return 'data:image/jpeg;base64,'.base64_encode($jpeg);
            }
        }

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }

    private static function looksLikeWebp(string $binary): bool
    {
        return str_starts_with($binary, 'RIFF') && str_contains(substr($binary, 0, 16), 'WEBP');
    }

    private static function guessMime(string $binary): ?string
    {
        if (str_starts_with($binary, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }
        if (self::looksLikeWebp($binary)) {
            return 'image/webp';
        }

        return null;
    }

    private static function encodeAsJpeg(string $binary, string $hint): ?string
    {
        $image = null;
        $tempFile = null;

        try {
            if ($hint === 'webp' && function_exists('imagecreatefromwebp')) {
                $tempFile = self::writeTemp($binary);
                $image = @imagecreatefromwebp($tempFile);
            } elseif ($hint === 'png' && function_exists('imagecreatefrompng')) {
                $tempFile = self::writeTemp($binary);
                $image = @imagecreatefrompng($tempFile);
            }

            if ($image === false || $image === null) {
                $image = @imagecreatefromstring($binary);
            }

            if ($image === false) {
                return null;
            }

            ob_start();
            imagejpeg($image, null, 88);
            $jpeg = ob_get_clean();
            imagedestroy($image);

            return $jpeg !== false && $jpeg !== '' ? $jpeg : null;
        } finally {
            if ($tempFile !== null && is_file($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    private static function writeTemp(string $binary): string
    {
        $path = tempnam(sys_get_temp_dir(), 'phoenix_img_');
        if ($path === false) {
            throw new \RuntimeException('No temp file for image conversion.');
        }
        file_put_contents($path, $binary);

        return $path;
    }

    private static function tempPath(string $binary): string
    {
        return self::writeTemp($binary);
    }
}
