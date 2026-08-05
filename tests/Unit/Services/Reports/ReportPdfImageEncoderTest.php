<?php

namespace Tests\Unit\Services\Reports;

use App\Services\Reports\ReportPdfImageEncoder;
use PHPUnit\Framework\TestCase;

class ReportPdfImageEncoderTest extends TestCase
{
    public function test_jpeg_passes_through_as_data_uri(): void
    {
        $jpeg = base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBEQCEAwEPwAB//9k=',
            true,
        );
        $this->assertNotFalse($jpeg);
        $uri = ReportPdfImageEncoder::toEmbeddedSrc($jpeg, 'image/jpeg');
        $this->assertNotNull($uri);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $uri);
    }

    public function test_png_with_alpha_passes_through_as_png_data_uri(): void
    {
        $png = $this->transparentPng(32, 32);
        $uri = ReportPdfImageEncoder::toEmbeddedSrc($png, 'image/png');
        $this->assertNotNull($uri);
        $this->assertStringStartsWith('data:image/png;base64,', $uri);

        $decoded = base64_decode(substr($uri, strlen('data:image/png;base64,')), true);
        $this->assertNotFalse($decoded);
        $this->assertSame($png, $decoded);
    }

    public function test_orientation_classifies_landscape_portrait_and_square(): void
    {
        $this->assertSame('landscape', ReportPdfImageEncoder::orientation($this->png(200, 100)));
        $this->assertSame('portrait', ReportPdfImageEncoder::orientation($this->png(100, 200)));
        $this->assertSame('square', ReportPdfImageEncoder::orientation($this->png(120, 120)));
    }

    public function test_dimensions_reads_png_size(): void
    {
        $dims = ReportPdfImageEncoder::dimensions($this->png(160, 90));
        $this->assertNotNull($dims);
        $this->assertSame(160, $dims['width']);
        $this->assertSame(90, $dims['height']);
    }

    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $this->assertNotFalse($image);
        $bg = imagecolorallocate($image, 200, 200, 200);
        imagefilledrectangle($image, 0, 0, $width, $height, $bg);
        ob_start();
        imagepng($image);
        $binary = ob_get_clean();
        imagedestroy($image);
        $this->assertNotFalse($binary);

        return $binary;
    }

    private function transparentPng(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $this->assertNotFalse($image);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
        $red = imagecolorallocatealpha($image, 220, 38, 38, 0);
        imagefilledellipse($image, (int) ($width / 2), (int) ($height / 2), (int) ($width / 2), (int) ($height / 2), $red);
        ob_start();
        imagepng($image);
        $binary = ob_get_clean();
        imagedestroy($image);
        $this->assertNotFalse($binary);

        return $binary;
    }
}
