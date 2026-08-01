<?php

namespace Tests\Unit\Services\Reports;

use App\Models\Routine;
use App\Models\RoutineExecution;
use App\Services\Reports\ReportHtmlBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDemoRoutine;
use Tests\TestCase;

class ReportHtmlBuilderProductionTest extends TestCase
{
    use CreatesDemoRoutine;
    use RefreshDatabase;

    private function routineWithExecution(): Routine
    {
        $technician = \App\Models\User::query()->where('email', 'misael.palos@mein-company.com')->firstOrFail();
        $routine = $this->demoRoutine($technician)->load(['routineType.formVersion', 'company', 'asset']);
        $execution = $routine->latestExecution ?? $routine->executions()->create([
            'performed_by' => $technician->id,
            'responses' => ['estado' => 'operativo', 'luces' => 'operativo'],
            'technician_comments' => 'Ok',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        $routine->setRelation('latestExecution', $execution);

        return $routine;
    }

    public function test_cover_contract_forces_omit_header_footer_in_production_html(): void
    {
        $this->seed();

        $routine = $this->routineWithExecution();
        $execution = $routine->latestExecution;

        $html = app(ReportHtmlBuilder::class)->build(
            $routine,
            $execution,
            [['type' => 'title', 'text' => 'Cuerpo']],
            [
                'cover_page' => [
                    'enabled' => true,
                    'title' => 'Portada contract',
                    'omit_header_footer' => false,
                ],
                'header' => ['enabled' => true, 'text' => 'HDR_CONTRACT'],
                'footer' => ['enabled' => true, 'text' => 'FTR_CONTRACT'],
            ],
        );

        $coverPos = strpos($html, 'report-cover');
        $mainPos = strpos($html, 'class="report-pdf-main');
        $this->assertNotFalse($coverPos);
        $this->assertNotFalse($mainPos);
        $coverSection = substr($html, $coverPos, $mainPos - $coverPos);
        $this->assertStringNotContainsString('HDR_CONTRACT', $coverSection);
        $this->assertTrue(
            str_contains($html, 'skipFirstPage') || str_contains($html, 'report-pdf--cover-omit-hf'),
            'Portada debe activar omit de chrome en hoja 1.',
        );
    }

    public function test_production_html_puts_cover_before_main_and_omits_header_on_cover(): void
    {
        $this->seed();

        $routine = $this->routineWithExecution();
        $execution = $routine->latestExecution;

        $components = [
            ['type' => 'paragraph', 'field' => 'luces'],
        ];
        $pageSettings = [
            'cover_page' => [
                'enabled' => true,
                'title' => 'Portada test',
                'omit_header_footer' => true,
            ],
            'header' => ['enabled' => true, 'text' => 'Encabezado PDF'],
            'footer' => ['enabled' => true, 'text' => 'Pie PDF'],
        ];

        $html = app(ReportHtmlBuilder::class)->build($routine, $execution, $components, $pageSettings);

        $coverPos = strpos($html, 'report-cover--sheet');
        $mainPos = strpos($html, 'class="report-pdf-main');
        $this->assertNotFalse($coverPos);
        $this->assertNotFalse($mainPos);
        $this->assertLessThan($mainPos, $coverPos);

        $coverSection = substr($html, $coverPos, $mainPos - $coverPos);
        $this->assertStringNotContainsString('Encabezado PDF', $coverSection);
        $this->assertStringContainsString('Portada test', $html);
        $this->assertStringNotContainsString('report-pdf-cover-page', $html);
        $this->assertStringContainsString('Encabezado PDF', $html);
        $this->assertStringContainsString('type="text/php"', $html);
        $this->assertStringNotContainsString('report-pdf-cover-page', $html);
    }

    public function test_page_number_script_offsets_from_start_at_excluding_leading_pages(): void
    {
        $this->seed();

        $routine = $this->routineWithExecution();
        $execution = $routine->latestExecution;

        $html = app(ReportHtmlBuilder::class)->build(
            $routine,
            $execution,
            [['type' => 'title', 'text' => 'Cuerpo']],
            [
                'cover_page' => [
                    'enabled' => true,
                    'title' => 'Portada',
                    'omit_header_footer' => true,
                ],
                'page_number' => ['enabled' => true, 'start_at' => 2],
            ],
        );

        $this->assertStringContainsString('page_script', $html);
        $this->assertStringContainsString('$displayNum = $PAGE_NUM - 2 + 1;', $html);
        $this->assertStringContainsString('$displayTotal = max(1, $PAGE_COUNT - 2 + 1);', $html);
        $this->assertStringNotContainsString('page_text', $html);
    }

    public function test_paragraph_includes_field_label_and_catalog_label(): void
    {
        $this->seed();

        $routine = $this->routineWithExecution();
        $execution = $routine->latestExecution;

        $fieldKey = 'luces';
        $this->assertNotNull(
            collect($routine->routineType?->formVersion?->schema['sections'] ?? [])
                ->flatMap(fn ($s) => $s['fields'] ?? [])
                ->firstWhere('key', $fieldKey),
            'Demo form should include luces field.',
        );

        $execution->update(['responses' => [$fieldKey => 'operativo']]);
        $execution->refresh();

        $html = app(ReportHtmlBuilder::class)->build(
            $routine,
            $execution,
            [['type' => 'paragraph', 'field' => $fieldKey]],
            [],
        );

        $this->assertStringContainsString('<table', $html);
        $this->assertStringNotContainsString('(luces)', $html);
        $this->assertStringContainsString('Operativo', $html);
    }

    public function test_paragraph_can_show_field_key_when_requested(): void
    {
        $this->seed();

        $routine = $this->routineWithExecution();
        $execution = $routine->latestExecution;
        $execution->update(['responses' => ['luces' => 'operativo']]);
        $execution->refresh();

        $html = app(ReportHtmlBuilder::class)->build(
            $routine,
            $execution,
            [['type' => 'paragraph', 'field' => 'luces', 'show_field_key' => true]],
            [],
        );

        $this->assertStringContainsString('(luces)', $html);
        $this->assertStringContainsString('Operativo', $html);
    }

    public function test_placeholder_strips_literal_page_token(): void
    {
        $this->seed();

        $routine = $this->routineWithExecution();
        $execution = $routine->latestExecution;

        $html = app(ReportHtmlBuilder::class)->build(
            $routine,
            $execution,
            [['type' => 'title', 'text' => 'Cuerpo']],
            [
                'footer' => ['enabled' => true, 'text' => 'Pie {{page}} confidencial'],
            ],
        );

        $this->assertStringContainsString('Pie  confidencial', $html);
        $this->assertStringNotContainsString('{{page}}', $html);
    }

    public function test_section_style_card_adds_table_class(): void
    {
        $this->seed();

        $routine = $this->routineWithExecution();
        $execution = $routine->latestExecution;
        $execution->update(['responses' => ['luces' => 'operativo']]);
        $execution->refresh();

        $html = app(ReportHtmlBuilder::class)->build(
            $routine,
            $execution,
            [['type' => 'paragraph', 'field' => 'luces']],
            ['theme' => ['section_style' => 'card']],
        );

        $this->assertStringContainsString('report-field-table--card', $html);
    }

    public function test_cover_pdf_does_not_start_with_blank_page(): void
    {
        $this->seed();

        $routine = $this->routineWithExecution();
        $execution = $routine->latestExecution;

        $html = app(ReportHtmlBuilder::class)->build(
            $routine,
            $execution,
            [['type' => 'title', 'text' => 'Cuerpo']],
            [
                'cover_page' => [
                    'enabled' => true,
                    'title' => 'Portada visible',
                    'omit_header_footer' => true,
                ],
                'header' => ['enabled' => true, 'text' => 'Hdr'],
                'footer' => ['enabled' => true, 'text' => 'Ftr'],
            ],
        );

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4');
        $pdf->getDomPDF()->set_option('isPhpEnabled', true);
        $pdf = $pdf->output();
        $pageMarkers = substr_count($pdf, '/Type /Page');
        $this->assertGreaterThanOrEqual(2, $pageMarkers);
        $this->assertLessThanOrEqual(3, $pageMarkers, 'Cover + short body should not produce extra blank pages.');
        $this->assertStringContainsString('report-cover--sheet', $html);
        $this->assertStringContainsString('Portada visible', $html);
    }

    public function test_subtitle_replaces_company_and_asset_placeholders(): void
    {
        $this->seed();

        $routine = $this->routineWithExecution();
        $execution = $routine->latestExecution;

        $companyName = $routine->company->name;
        $assetTag = $routine->asset->tag;

        $html = app(ReportHtmlBuilder::class)->build(
            $routine,
            $execution,
            [['type' => 'subtitle', 'text' => '{{company}} · {{asset_tag}}']],
            [],
        );

        $this->assertStringContainsString($companyName, $html);
        $this->assertStringContainsString($assetTag, $html);
        $this->assertStringNotContainsString('{{company}}', $html);
        $this->assertStringNotContainsString('{{asset_tag}}', $html);
    }

    public function test_cover_body_converts_editor_html_with_literal_markdown(): void
    {
        $html = app(ReportHtmlBuilder::class)->buildPreviewPdfHtml(
            [['type' => 'title', 'text' => 'Cuerpo']],
            [
                'cover_page' => [
                    'enabled' => true,
                    'title' => 'Informe',
                    'body' => '<p>Activo: **{{asset_tag}}**\\n\\nRutina de mantenimiento documentada.</p>',
                    'omit_header_footer' => true,
                ],
                'theme' => [
                    'colors' => [
                        'cover_bg' => '#1e3a5f',
                        'cover_text' => '#f8fafc',
                    ],
                ],
            ],
        );

        $this->assertStringContainsString('<strong>DEMO-001</strong>', $html);
        $this->assertStringNotContainsString('**DEMO-001**', $html);
        $this->assertStringNotContainsString('\\n', $html);
        $this->assertStringContainsString('report-cover-body', $html);
        $this->assertStringContainsString('report-cover-page__bg', $html);
        $this->assertStringContainsString('min-height: 297mm', $html);
        $this->assertStringContainsString('margin: -18mm -14mm -22mm -14mm', $html);
        $this->assertStringContainsString('report-pdf-main--body', $html);
    }

    public function test_themed_cover_pdf_is_cover_plus_content_without_blank_page(): void
    {
        $html = app(ReportHtmlBuilder::class)->buildPreviewPdfHtml(
            [['type' => 'title', 'text' => 'Cuerpo']],
            [
                'cover_page' => [
                    'enabled' => true,
                    'title' => 'Portada',
                    'omit_header_footer' => true,
                ],
                'header' => ['enabled' => true, 'text' => 'Encabezado'],
                'footer' => ['enabled' => true, 'text' => 'Pie'],
                'theme' => [
                    'colors' => [
                        'cover_bg' => '#1e3a5f',
                        'cover_text' => '#f8fafc',
                    ],
                ],
            ],
        );

        $this->assertStringContainsString('if ($PAGE_NUM >= 2)', $html);

        $probe = \Tests\Support\ReportPdfLayoutProbe::fromHtml($html, true);

        $this->assertSame(2, $probe->pageCount, 'Portada temática + cuerpo debe ser exactamente 2 páginas.');
        if (! $probe->bboxAvailable()) {
            $this->markTestSkipped('pdftotext no disponible para validar layout.');
        }
        $this->assertTrue($probe->hasTextOnPage(1, 'Portada'));
        $this->assertFalse($probe->hasTextOnPage(1, 'Encabezado'));
        $this->assertFalse($probe->hasTextOnPage(1, 'Pie'));
        $this->assertTrue($probe->hasTextOnPage(2, 'Cuerpo'));
        $this->assertTrue($probe->hasTextOnPage(2, 'Encabezado'));
        $minX = $probe->minXOnPage(2);
        $this->assertNotNull($minX);
        $this->assertGreaterThanOrEqual(35.0, $minX, 'El cuerpo debe respetar margen horizontal (~14mm).');
    }

    public function test_cover_respects_fixed_date_on_pdf_html(): void
    {
        $html = app(ReportHtmlBuilder::class)->buildPreviewPdfHtml(
            [['type' => 'title', 'text' => 'Cuerpo']],
            [
                'cover_page' => [
                    'enabled' => true,
                    'title' => 'Informe',
                    'show_date' => true,
                    'date_fixed' => '2025-03-15',
                    'omit_header_footer' => true,
                ],
            ],
        );

        $this->assertStringContainsString('15/03/2025', $html);
    }

    public function test_body_pages_keep_default_page_margins_when_cover_disabled(): void
    {
        $html = app(ReportHtmlBuilder::class)->buildPreviewPdfHtml(
            [['type' => 'title', 'text' => 'Solo cuerpo']],
            ['cover_page' => ['enabled' => false]],
        );

        $this->assertStringContainsString('@page { margin: 18mm 14mm 22mm 14mm', $html);
        $this->assertStringNotContainsString('@page report-content', $html);
        $this->assertStringNotContainsString('page: report-cover', $html);
        $this->assertStringNotContainsString('height: 773pt', $html);
    }
}
