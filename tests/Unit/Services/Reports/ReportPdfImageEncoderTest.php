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
}
