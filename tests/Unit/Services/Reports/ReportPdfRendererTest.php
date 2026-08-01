<?php

namespace Tests\Unit\Services\Reports;

use App\Services\Reports\ReportPdfDocument;
use App\Services\Reports\ReportPdfRenderer;
use Tests\TestCase;

class ReportPdfRendererTest extends TestCase
{
    public function test_dompdf_driver_renders_pdf_bytes(): void
    {
        config(['phoenix.reports.pdf_driver' => 'dompdf']);

        $html = '<!DOCTYPE html><html><body><h1>Hola PDF</h1></body></html>';
        $pdf = app(ReportPdfRenderer::class)->htmlToPdf($html);

        $this->assertNotSame('', $pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_auto_falls_back_to_dompdf_without_chrome(): void
    {
        config([
            'phoenix.reports.pdf_driver' => 'browsershot',
            'phoenix.reports.chrome_path' => '/nonexistent/chromium-forced',
        ]);

        $renderer = new class extends ReportPdfRenderer
        {
            public function browsershotAvailable(): bool
            {
                return false;
            }
        };

        $this->assertSame('dompdf', $renderer->driver());
    }

    public function test_document_chrome_metadata_is_preserved(): void
    {
        $doc = new ReportPdfDocument('<html></html>', null, null, [
            'header' => 'Hdr',
            'footer' => 'Ftr',
            'page_numbers' => true,
            'page_number_start_at' => 2,
            'skip_first_page_chrome' => true,
        ]);

        $this->assertSame('Hdr', $doc->headerText());
        $this->assertTrue($doc->pageNumbersEnabled());
        $this->assertSame(2, $doc->pageNumberStartAt());
        $this->assertTrue($doc->skipFirstPageChrome());
    }
}
