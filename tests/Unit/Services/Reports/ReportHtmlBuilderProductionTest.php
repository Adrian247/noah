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
        $mainPos = strpos($html, '<div class="report-pdf-main">');
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
        $this->assertStringContainsString('luces', $html);
        $this->assertStringContainsString('Operativo', $html);
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
}
