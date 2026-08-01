<?php

namespace Tests\Unit\Services\Reports;

use App\Services\Reports\ReportPageSettingsNormalizer;
use Tests\TestCase;

class ReportPageSettingsNormalizerTest extends TestCase
{
    public function test_forces_omit_header_footer_when_cover_enabled(): void
    {
        $out = (new ReportPageSettingsNormalizer)->normalize([
            'cover_page' => [
                'enabled' => true,
                'omit_header_footer' => false,
                'title' => 'Portada',
            ],
            'footer' => ['enabled' => true, 'text' => 'Pie {{page}}'],
        ]);

        $this->assertSame(ReportPageSettingsNormalizer::SCHEMA_VERSION, $out['schema_version']);
        $this->assertTrue($out['cover_page']['omit_header_footer']);
        $this->assertSame('Pie ', $out['footer']['text']);
        $this->assertSame(2, $out['page_number']['start_at']);
    }

    public function test_strips_invalid_colors_and_clamps_typography(): void
    {
        $out = (new ReportPageSettingsNormalizer)->normalize([
            'typography' => ['title_pt' => 200, 'body_pt' => 2],
            'theme' => [
                'section_style' => 'weird',
                'colors' => [
                    'primary' => '#ABC',
                    'accent' => 'red',
                ],
            ],
        ]);

        $this->assertSame(72, $out['typography']['title_pt']);
        $this->assertSame(6, $out['typography']['body_pt']);
        $this->assertSame('minimal', $out['theme']['section_style']);
        $this->assertSame('#aabbcc', $out['theme']['colors']['primary']);
        $this->assertArrayNotHasKey('accent', $out['theme']['colors']);
    }
}
