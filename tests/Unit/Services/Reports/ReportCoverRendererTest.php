<?php

namespace Tests\Unit\Services\Reports;

use App\Services\Reports\ReportCoverRenderer;
use Tests\TestCase;

class ReportCoverRendererTest extends TestCase
{
    public function test_cover_renderer_always_omits_hf_when_enabled(): void
    {
        $renderer = new ReportCoverRenderer;
        $this->assertTrue($renderer->omitsHeaderFooter(['enabled' => true, 'omit_header_footer' => false]));
        $this->assertFalse($renderer->omitsHeaderFooter(['enabled' => false, 'omit_header_footer' => false]));
    }

    public function test_render_returns_empty_when_disabled(): void
    {
        $html = (new ReportCoverRenderer)->render(
            ['enabled' => false, 'title' => 'X'],
            'Co',
            'TAG',
            1,
            'DejaVu Sans',
            22,
            16,
        );

        $this->assertSame('', $html);
    }
}
