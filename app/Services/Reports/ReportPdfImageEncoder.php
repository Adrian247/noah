<?php

namespace App\Services\Reports;

/**
 * DomPDF (vía GD) no siempre acepta WebP en data-URI; normaliza a JPEG para el PDF.
 */
final class ReportPdfImageEncoder
{
    /**
     * @return array{width: int, height: int}|null
     */
    public static function dimensions(string $binary): ?array
    {
        $info = @getimagesizefromstring($binary);
        if ($info === false || ! isset($info[0], $info[1])) {
            return null;
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        if ($width < 1 || $height < 1) {
            return null;
        }

        [$width, $height] = self::applyExifDisplaySize($binary, $width, $height);

        return ['width' => $width, 'height' => $height];
    }

    /**
     * Clasifica orientación visual para layout de reportes.
     *
     * @return 'landscape'|'portrait'|'square'
     */
    public static function orientation(string $binary): string
    {
        $dims = self::dimensions($binary);
        if ($dims === null) {
            return 'square';
        }

        $ratio = $dims['width'] / $dims['height'];
        if ($ratio >= 1.12) {
            return 'landscape';
        }
        if ($ratio <= 0.88) {
            return 'portrait';
        }

        return 'square';
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function applyExifDisplaySize(string $binary, int $width, int $height): array
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($binary, "\xFF\xD8\xFF")) {
            return [$width, $height];
        }

        $temp = self::writeTemp($binary);
        try {
            $exif = @exif_read_data($temp);
            $orientation = (int) ($exif['Orientation'] ?? 1);
            // 5–8 = rotación 90°: intercambiar eje para layout.
            if (in_array($orientation, [5, 6, 7, 8], true)) {
                return [$height, $width];
            }
        } finally {
            if (is_file($temp)) {
                @unlink($temp);
            }
        }

        return [$width, $height];
    }

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
