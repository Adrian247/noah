<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\Storage;

final class DemoClientLogoGenerator
{
    public static function syncForClient(int $clientId): string
    {
        $relativePath = 'clients/'.$clientId.'/logo.png';
        $binary = self::renderPng();
        if ($binary === '') {
            return '';
        }

        Storage::disk('public')->put($relativePath, $binary);

        return $relativePath;
    }

    public static function renderPng(): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return '';
        }

        $size = 256;
        $image = imagecreatetruecolor($size, $size);
        if ($image === false) {
            return '';
        }

        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        $navy = imagecolorallocate($image, 15, 23, 42);
        $navyMid = imagecolorallocate($image, 30, 41, 59);
        $gold = imagecolorallocate($image, 201, 162, 39);
        $goldLight = imagecolorallocate($image, 245, 230, 180);
        $white = imagecolorallocate($image, 250, 250, 252);

        $cx = (int) ($size / 2);
        $cy = (int) ($size / 2);

        imagefilledellipse($image, $cx, $cy, 220, 220, $navyMid);
        imagefilledellipse($image, $cx, $cy, 200, 200, $navy);
        imagearc($image, $cx, $cy, 200, 200, 0, 360, $gold);
        imagearc($image, $cx, $cy, 184, 184, 0, 360, $goldLight);

        imagefilledellipse($image, $cx, $cy - 8, 72, 72, $gold);
        imagefilledellipse($image, $cx, $cy - 8, 58, 58, $navy);

        imagestring($image, 5, $cx - 16, $cy - 20, 'AE', $goldLight);

        imagearc($image, $cx, $cy + 42, 120, 48, 0, 180, $gold);
        imagefilledellipse($image, $cx - 38, $cy + 52, 22, 22, $goldLight);
        imagefilledellipse($image, $cx + 38, $cy + 52, 22, 22, $goldLight);

        ob_start();
        imagepng($image);
        $binary = ob_get_clean() ?: '';
        imagedestroy($image);

        return $binary;
    }

    public static function writeAssetFile(): void
    {
        $path = database_path('seeders/assets/demo-client-logo.png');
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, self::renderPng());
    }
}
