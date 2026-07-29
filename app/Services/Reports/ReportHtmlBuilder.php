<?php

namespace App\Services\Reports;

use App\Models\Client;
use App\Models\Company;
use App\Models\FormOptionCatalog;
use App\Models\FormVersion;
use App\Models\ReportSectionTemplate;
use App\Models\ReportTemplate;
use App\Models\Routine;
use App\Models\RoutineExecution;
use App\Services\Forms\PhotoResponseNormalizer;
use App\Services\Platform\PlatformTenantService;
use Illuminate\Support\Facades\Storage;

class ReportHtmlBuilder
{
    /** @var array<string, mixed>|null */
    private ?array $documentTheme = null;

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @param  array<string, mixed>  $pageSettings
     */
    public function build(Routine $routine, RoutineExecution $execution, array $components, array $pageSettings = [], ?int $reportTemplateVersionId = null): string
    {
        $routine->loadMissing(['routineType.formVersion', 'company', 'asset']);

        $reportTemplateId = null;
        if ($reportTemplateVersionId !== null) {
            $version = \App\Models\ReportTemplateVersion::query()->find($reportTemplateVersionId);
            $reportTemplateId = $version?->report_template_id;
        }

        return $this->renderDocument($routine, $execution, $components, $pageSettings, false, false, $reportTemplateId);
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @param  array<string, mixed>  $pageSettings
     */
    public function buildPreview(array $components, array $pageSettings = [], array $sampleResponses = [], ?int $reportTemplateId = null, bool $thumbnail = false): string
    {
        if ($sampleResponses === [] && $reportTemplateId !== null) {
            $sampleResponses = app(ReportSampleDataFactory::class)->buildForPreview($components, $reportTemplateId);
        }

        $routine = new Routine([
            'id' => 0,
            'company_id' => 0,
        ]);
        $company = null;

        if ($reportTemplateId !== null) {
            $template = ReportTemplate::query()->find($reportTemplateId);
            if ($template !== null) {
                $routine->company_id = $template->company_id;
                $company = Company::query()->find($template->company_id);
            }
        }

        if ($company === null) {
            $company = new Company(['name' => 'Empresa demo']);
        }

        $routine->setRelation('company', $company);
        $routine->setRelation('asset', (object) ['tag' => 'DEMO-001']);

        $execution = new RoutineExecution([
            'responses' => $sampleResponses,
            'technician_comments' => (string) ($sampleResponses['technician_comments'] ?? 'Texto técnico de muestra.'),
            'corrected_comments' => (string) ($sampleResponses['corrected_comments'] ?? 'Comentario corregido de ejemplo.'),
        ]);

        return $this->renderDocument($routine, $execution, $components, $pageSettings, true, $thumbnail, $reportTemplateId);
    }

    /**
     * HTML listo para DomPDF (misma salida que un informe generado), con datos de ejemplo.
     *
     * @param  array<int, array<string, mixed>>  $components
     * @param  array<string, mixed>  $pageSettings
     */
    public function buildPreviewPdfHtml(array $components, array $pageSettings = [], ?int $reportTemplateId = null): string
    {
        $sampleResponses = [];
        if ($reportTemplateId !== null) {
            $sampleResponses = app(ReportSampleDataFactory::class)->buildForPreview($components, $reportTemplateId);
        }

        $routine = new Routine([
            'id' => 0,
            'company_id' => 0,
        ]);
        $company = null;

        if ($reportTemplateId !== null) {
            $template = ReportTemplate::query()->find($reportTemplateId);
            if ($template !== null) {
                $routine->company_id = $template->company_id;
                $company = Company::query()->find($template->company_id);
            }
        }

        if ($company === null) {
            $company = new Company(['name' => 'Empresa demo']);
        }

        $routine->setRelation('company', $company);
        $routine->setRelation('asset', (object) ['tag' => 'DEMO-001']);

        $execution = new RoutineExecution([
            'responses' => $sampleResponses,
            'technician_comments' => (string) ($sampleResponses['technician_comments'] ?? 'Texto técnico de muestra.'),
            'corrected_comments' => (string) ($sampleResponses['corrected_comments'] ?? 'Comentario corregido de ejemplo.'),
        ]);

        return $this->renderDocument($routine, $execution, $components, $pageSettings, false, false, $reportTemplateId);
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @param  array<string, mixed>  $pageSettings
     */
    private function renderDocument(
        Routine $routine,
        RoutineExecution $execution,
        array $components,
        array $pageSettings,
        bool $isPreview,
        bool $thumbnail = false,
        ?int $reportTemplateId = null,
    ): string {
        $company = $routine->company;
        $companyForLogo = $company instanceof Company ? $company : null;
        $asset = $routine->asset;
        $body = '';

        if ($components === []) {
            $components = [
                ['type' => 'title', 'text' => 'Reporte de mantenimiento'],
                ['type' => 'paragraph', 'field' => 'corrected_comments'],
            ];
        }

        $typography = $pageSettings['typography'] ?? [];
        $bodyPt = (int) ($typography['body_pt'] ?? 11);
        $titlePt = (int) ($typography['title_pt'] ?? 22);
        $subtitlePt = (int) ($typography['subtitle_pt'] ?? 16);

        $font = $this->fontFamily($pageSettings['font_family'] ?? 'roboto');
        $this->documentTheme = is_array($pageSettings['theme'] ?? null) ? $pageSettings['theme'] : null;
        $routineId = (int) ($routine->id ?? 0);
        $fieldContext = $this->resolveFieldContext($routine, $reportTemplateId);

        $coverHtml = $this->renderCoverPage(
            $pageSettings['cover_page'] ?? null,
            $company?->name ?? 'Phoenix',
            $asset?->tag ?? 'DEMO-001',
            $routineId,
            $font,
            $titlePt,
            $subtitlePt,
            $pageSettings,
            $companyForLogo,
            $execution,
        );

        foreach ($components as $component) {
            $body .= $this->renderComponent($component, $routine, $execution, $company?->name ?? 'Phoenix', $asset?->tag ?? null, $pageSettings, $fieldContext);
        }

        $coverSettings = $pageSettings['cover_page'] ?? [];
        $coverEnabled = ! empty($coverSettings['enabled']);
        $omitHeaderOnCover = $this->coverOmitsHeaderFooter($coverSettings);
        $useDompdfScriptChrome = ! $isPreview && $this->shouldUsePdfChrome($pageSettings);

        $headerHtml = $this->renderHeaderFooter($pageSettings['header'] ?? null, $company?->name ?? 'Phoenix', $routineId, 'header', $asset?->tag ?? '', $isPreview);
        $footerHtml = $this->renderHeaderFooter($pageSettings['footer'] ?? null, $company?->name ?? 'Phoenix', $routineId, 'footer', $asset?->tag ?? '', $isPreview);
        $pageNumber = $pageSettings['page_number'] ?? ['enabled' => false, 'start_at' => 1];
        $skipCoverPageForChrome = $coverEnabled && $omitHeaderOnCover;

        $previewBanner = $isPreview && ! $thumbnail
            ? '<div class="report-preview-banner">Vista previa — datos de ejemplo</div>'
            : '';

        $metaLine = 'Phoenix · '.$this->e($company?->name ?? 'Phoenix').' · Rutina #'.$routineId;
        $isolatePdfCoverPage = ! $isPreview && $coverEnabled && $this->hasThemedCoverBackground();

        if ($isPreview) {
            return $this->wrapHtmlDocument(
                $font,
                $bodyPt,
                $titlePt,
                $subtitlePt,
                $this->assemblePreviewBody(
                    $previewBanner,
                    $coverHtml,
                    $coverEnabled,
                    $omitHeaderOnCover,
                    $headerHtml,
                    $footerHtml,
                    $metaLine,
                    $body,
                    $thumbnail,
                    $pageSettings,
                ),
                true,
                $thumbnail,
                false,
            );
        }

        return $this->wrapHtmlDocument(
            $font,
            $bodyPt,
            $titlePt,
            $subtitlePt,
            $this->assembleProductionBody(
                $coverHtml,
                $coverEnabled,
                $omitHeaderOnCover,
                $headerHtml,
                $footerHtml,
                $metaLine,
                $body,
                $pageSettings,
                $pageNumber,
                $skipCoverPageForChrome,
                $company?->name ?? 'Phoenix',
                $routineId,
                $asset?->tag ?? '',
                $useDompdfScriptChrome,
            ),
            false,
            false,
            $isolatePdfCoverPage,
        );
    }

    private function assembleProductionBody(
        string $coverHtml,
        bool $coverEnabled,
        bool $omitHeaderOnCover,
        string $headerHtml,
        string $footerHtml,
        string $metaLine,
        string $body,
        array $pageSettings,
        array $pageNumber,
        bool $skipCoverPageForChrome,
        string $companyName,
        int $routineId,
        string $assetTag,
        bool $useDompdfScriptChrome,
    ): string {
        $bodyClass = 'report-pdf';
        if ($coverEnabled) {
            $bodyClass .= ' report-pdf--with-cover';
        }
        if ($coverEnabled && $omitHeaderOnCover) {
            $bodyClass .= ' report-pdf--cover-omit-hf';
        }

        $chromeScript = '';
        $mainLead = '';
        if ($useDompdfScriptChrome) {
            $chromeScript = $this->dompdfChromeScript(
                $pageSettings,
                $pageNumber,
                $companyName,
                $routineId,
                $assetTag,
                $skipCoverPageForChrome,
            );
        } elseif ($this->shouldRenderPdfHeaderBlock($pageSettings)) {
            $mainLead = $this->renderPdfHeaderBlock(
                $pageSettings,
                $companyName,
                $routineId,
                $assetTag,
            );
        }

        $html = '<body class="'.$bodyClass.'">';

        if ($coverEnabled && $coverHtml !== '') {
            $html .= $coverHtml;
        }

        $html .= '<div class="report-pdf-main'.($coverEnabled ? ' report-pdf-main--body' : '').'">';
        $html .= $mainLead;
        if (! $coverEnabled) {
            $html .= '<div class="meta">'.$metaLine.'</div>';
        }
        $html .= $body;
        $html .= '</div>'.$chromeScript.'</body>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $pageSettings
     */
    private function shouldRenderPdfHeaderBlock(array $pageSettings): bool
    {
        return ! empty($pageSettings['header']['enabled']) && trim((string) ($pageSettings['header']['text'] ?? '')) !== '';
    }

    /**
     * Encabezado estático (sin script PHP DomPDF) para evitar páginas en blanco.
     *
     * @param  array<string, mixed>  $pageSettings
     */
    private function renderPdfHeaderBlock(
        array $pageSettings,
        string $companyName,
        int $routineId,
        string $assetTag,
    ): string {
        $plain = $this->plainTextFromTemplate(
            (string) ($pageSettings['header']['text'] ?? ''),
            $companyName,
            $routineId,
            $assetTag,
        );

        return '<div style="font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #444; border-bottom: 1px solid #ddd; padding-bottom: 6px; margin-bottom: 14px;">'
            .$this->e($plain).'</div>';
    }

    /**
     * @param  array<string, mixed>  $pageSettings
     */
    private function shouldUsePdfChrome(array $pageSettings): bool
    {
        if (! empty($pageSettings['header']['enabled'])) {
            return true;
        }
        if (! empty($pageSettings['footer']['enabled'])) {
            return true;
        }

        return ! empty($pageSettings['page_number']['enabled']);
    }

    /**
     * Encabezado, pie y numeración en páginas de contenido (omite portada cuando aplica).
     *
     * @param  array<string, mixed>  $pageSettings
     * @param  array<string, mixed>  $pageNumber
     */
    private function dompdfChromeScript(
        array $pageSettings,
        array $pageNumber,
        string $companyName,
        int $routineId,
        string $assetTag,
        bool $skipFirstPage,
    ): string {
        $headerPlain = '';
        if (! empty($pageSettings['header']['enabled'])) {
            $headerPlain = $this->plainTextFromTemplate(
                (string) ($pageSettings['header']['text'] ?? ''),
                $companyName,
                $routineId,
                $assetTag,
            );
        }
        $footerPlain = '';
        if (! empty($pageSettings['footer']['enabled'])) {
            $footerPlain = $this->plainTextFromTemplate(
                (string) ($pageSettings['footer']['text'] ?? ''),
                $companyName,
                $routineId,
                $assetTag,
            );
        }

        $startPage = $skipFirstPage ? 2 : 1;
        $pageNumbersOn = ! empty($pageNumber['enabled']);
        $countStartAt = max(1, (int) ($pageNumber['start_at'] ?? 1));
        if ($skipFirstPage) {
            $countStartAt = max($countStartAt, 2);
        }
        $headerLit = var_export($headerPlain, true);
        $footerLit = var_export($footerPlain, true);
        $pageNumbersLit = $pageNumbersOn ? 'true' : 'false';

        // page_script runs once per finished page with correct $PAGE_NUM / $PAGE_COUNT.
        // page_text() with pre-built strings freezes the last page values on every sheet.
        $pageScript = '$font = $fontMetrics->get_font("helvetica", "normal");'
            ."\n".'$size = 9;'
            ."\n".'$muted = array(0.35, 0.35, 0.35);'
            ."\n".'if ($PAGE_NUM >= '.$startPage.') {'
            ."\n".'    if ('.$headerLit.' !== \'\') {'
            ."\n".'        $pdf->text(48, 28, '.$headerLit.', $font, $size, $muted);'
            ."\n".'    }'
            ."\n".'    if ('.$footerLit.' !== \'\') {'
            ."\n".'        $pdf->text(48, $pdf->get_height() - 44, '.$footerLit.', $font, $size, $muted);'
            ."\n".'    }'
            ."\n".'}'
            ."\n".'if ('.$pageNumbersLit.' && $PAGE_NUM >= '.$countStartAt.') {'
            ."\n".'    $displayNum = $PAGE_NUM - '.$countStartAt.' + 1;'
            ."\n".'    $displayTotal = max(1, $PAGE_COUNT - '.$countStartAt.' + 1);'
            ."\n".'    $pdf->text('
            ."\n".'        $pdf->get_width() - 120,'
            ."\n".'        $pdf->get_height() - 36,'
            ."\n".'        "Página " . $displayNum . " de " . $displayTotal,'
            ."\n".'        $font,'
            ."\n".'        $size,'
            ."\n".'        array(0.4, 0.4, 0.4)'
            ."\n".'    );'
            ."\n".'}';

        $pageScriptLit = var_export($pageScript, true);

        return <<<HTML
<script type="text/php">
if (isset(\$pdf)) {
    \$pdf->page_script({$pageScriptLit});
}
</script>
HTML;
    }

    private function plainTextFromTemplate(string $text, string $companyName, int $routineId, string $assetTag): string
    {
        $replaced = $this->replacePlaceholders($text, $companyName, $assetTag, $routineId);
        $html = $this->formatText($replaced);

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function assemblePreviewBody(
        string $previewBanner,
        string $coverHtml,
        bool $coverEnabled,
        bool $omitHeaderOnCover,
        string $headerHtml,
        string $footerHtml,
        string $metaLine,
        string $body,
        bool $thumbnail,
        array $pageSettings,
    ): string {
        if ($thumbnail) {
            $firstPage = $this->assembleFirstPreviewPage(
                $coverHtml,
                $coverEnabled,
                $omitHeaderOnCover,
                $headerHtml,
                $footerHtml,
                $metaLine,
                $body,
                $pageSettings,
            );

            return '<body class="report-thumb-body"><div class="report-thumb-fit">'.$firstPage.'</div></body>';
        }

        $html = '<body class="report-preview"><div class="report-preview-fit">'.$previewBanner;

        if ($coverEnabled && $coverHtml !== '') {
            $html .= $this->themedCoverPageOpeningTag($pageSettings);
            if (! $omitHeaderOnCover) {
                $html .= $headerHtml;
            }
            $html .= $coverHtml;
            if (! $omitHeaderOnCover) {
                $html .= $footerHtml;
            }
            $html .= '</div>';
        }

        $html .= '<div class="report-page report-page--content">';
        $html .= $headerHtml.'<div class="meta">'.$metaLine.'</div>'.$body.$footerHtml.'</div></div></body>';

        return $html;
    }

    private function assembleFirstPreviewPage(
        string $coverHtml,
        bool $coverEnabled,
        bool $omitHeaderOnCover,
        string $headerHtml,
        string $footerHtml,
        string $metaLine,
        string $body,
        array $pageSettings,
    ): string {
        if ($coverEnabled && $coverHtml !== '') {
            $html = $this->themedCoverPageOpeningTag($pageSettings);
            if (! $omitHeaderOnCover) {
                $html .= $headerHtml;
            }
            $html .= $coverHtml;
            if (! $omitHeaderOnCover) {
                $html .= $footerHtml;
            }

            return $html.'</div>';
        }

        $html = '<div class="report-page report-page--content">';
        $html .= $headerHtml.'<div class="meta">'.$metaLine.'</div>'.$body.$footerHtml;

        return $html.'</div>';
    }

    private function wrapHtmlDocument(
        string $font,
        int $bodyPt,
        int $titlePt,
        int $subtitlePt,
        string $inner,
        bool $isPreview,
        bool $thumbnail,
        bool $isolatePdfCoverPage = false,
    ): string {
        $previewCss = $isPreview ? $this->previewStyles($thumbnail) : '';
        $themedCoverPdfCss = (! $isPreview && $isolatePdfCoverPage)
            ? '
body.report-pdf .report-cover-page--sheet {
  position: relative;
  margin: -18mm -14mm -22mm -14mm;
  width: calc(100% + 28mm);
  min-height: 297mm;
  padding: 0;
  overflow: hidden;
  page-break-after: always;
  page-break-inside: avoid;
  box-sizing: border-box;
}
body.report-pdf .report-cover-page__bg {
  position: absolute;
  left: 0;
  top: 0;
  right: 0;
  bottom: 0;
  width: 100%;
  height: 100%;
  z-index: 0;
}
body.report-pdf .report-cover-page__content {
  position: relative;
  z-index: 1;
  width: 100%;
  text-align: center;
}
body.report-pdf .report-cover-page--sheet table.report-cover-layout {
  width: 100%;
  border-collapse: collapse;
}
body.report-pdf .report-cover-page--sheet .report-cover-hero-cell {
  text-align: center;
  vertical-align: middle;
  padding: 18mm 14mm;
}
body.report-pdf .report-cover-page--sheet .report-cover-hero-inner {
  width: 100%;
  text-align: center;
  margin: 0 auto;
}
body.report-pdf .report-cover-page--sheet h1,
body.report-pdf .report-cover-page--sheet h2.report-subtitle,
body.report-pdf .report-cover-page--sheet .report-cover-date,
body.report-pdf .report-cover-page--sheet .report-cover-body,
body.report-pdf .report-cover-page--sheet .report-cover-body p {
  text-align: center !important;
  margin-left: auto;
  margin-right: auto;
}
'
            : '';
        $pdfCss = $isPreview ? '' : '
@page { margin: 18mm 14mm 22mm 14mm; size: A4 portrait; }
body.report-pdf { margin: 0; padding: 0; font-family: DejaVu Sans, sans-serif; }
.report-field-line { margin: 0 0 8px; line-height: 1.45; }
.report-field-label { font-weight: 700; color: #111; }
.report-field-value { color: #111; }
.report-field-hint { font-size: 8.5pt; color: #666; margin: 0 0 8px; }
body.report-pdf .report-cover,
body.report-pdf .report-cover.report-cover--sheet {
  display: block !important;
  flex: none !important;
  min-height: 0 !important;
  text-align: center;
  padding: 20mm 14mm 16mm;
  box-sizing: border-box;
  page-break-after: always;
  page-break-inside: avoid;
}
body.report-pdf .report-cover.report-cover--fullbleed {
  page-break-after: avoid !important;
}
body.report-pdf .report-cover-body { font-size: 10pt; line-height: 1.5; text-align: center !important; margin-top: 16px; max-width: 100%; }
body.report-pdf .report-cover-body p,
body.report-pdf .report-cover-body div,
body.report-pdf .report-cover-body span { text-align: center !important; margin: 0.35em 0; }
body.report-pdf .report-cover-image { display: block; max-width: 120px; max-height: 120px; margin: 0 auto 16px; }
body.report-pdf .report-pdf-main { margin: 0; padding: 0; }
'.$themedCoverPdfCss;

        $previewHeaderCss = $isPreview ? '
.report-preview .report-header,
.report-preview .report-footer { position: static; margin: 0 0 12px; border: 0; border-bottom: 1px solid #ddd; padding-bottom: 8px; }
.report-preview .report-footer { border-bottom: 0; border-top: 1px solid #ddd; padding-top: 8px; margin-top: 16px; }
' : '';

        $bodyOpen = str_starts_with(trim($inner), '<body') ? '' : '<body>';
        $bodyClose = str_contains($inner, '</body>') ? '' : '</body>';
        $fontLink = $isPreview
            ? '<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&family=Source+Sans+3:wght@400;700&display=swap" rel="stylesheet">'
            : '';

        $themeCss = $this->themeStyles();
        $textColor = $this->themeColor('text', '#111827');
        $mutedColor = $this->themeColor('muted', '#64748b');
        $headingColor = $this->themeColor('secondary', '#0f172a');
        $subtitleColor = $this->themeColor('primary', '#d97706');
        $accentColor = $this->themeColor('accent', '#f59e0b');
        $borderColor = $this->themeColor('border', '#e2e8f0');

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
{$fontLink}
<style>
body { font-family: {$font}; font-size: {$bodyPt}pt; color: {$textColor}; }
h1 { font-size: {$titlePt}pt; margin-bottom: 12px; color: {$headingColor}; }
h2.report-subtitle { font-size: {$subtitlePt}pt; margin-bottom: 10px; font-weight: 600; color: {$subtitleColor}; border-bottom: 2px solid {$accentColor}; padding-bottom: 4px; }
.meta { color: {$mutedColor}; font-size: 9pt; margin-bottom: 16px; }
p { line-height: 1.5; margin: 0 0 10px; }
.report-cover { display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; min-height: 240mm; padding: 48px; box-sizing: border-box; }
.report-cover--themed h1 { color: inherit !important; margin: 0.15em 0 0.25em; font-weight: 700; border: 0; }
.report-cover--themed h2.report-subtitle { color: inherit !important; border: 0 !important; padding: 0 !important; margin: 0.35em 0 !important; font-weight: 600; }
.report-cover--themed .report-cover-date { font-size: 9pt; opacity: 0.82; margin: 0.5em 0 0.75em; }
.report-cover-page--sheet { position: relative; width: 100%; min-height: 297mm; box-sizing: border-box; }
.report-cover-page__bg { position: absolute; left: 0; top: 0; right: 0; bottom: 0; z-index: 0; }
.report-cover-page__content { position: relative; z-index: 1; width: 100%; }
.report-cover-page--sheet h1,
.report-cover-page--sheet h2.report-subtitle,
.report-cover-page--sheet .report-cover-date,
.report-cover-page--sheet .report-cover-body,
.report-cover-page--sheet .report-cover-body p { text-align: center !important; }
.report-cover-image { display: block; max-width: 160px; max-height: 160px; width: auto; height: auto; object-fit: contain; margin: 0 auto 28px; }
.report-cover-body { margin-top: 24px; text-align: center !important; max-width: 36rem; margin-left: auto; margin-right: auto; }
.report-cover-body p,
.report-cover-body div { text-align: center !important; margin: 0.35em 0; }
.report-image-caption { font-size: 9pt; color: #555; margin-top: 4px; }
.report-gallery { margin: 12px 0; }
.report-header, .report-footer { font-size: 9pt; color: #444; width: 100%; }
.report-divider { border: 0; border-top: 1px solid {$borderColor}; margin: 16px 0; }
.report-rich table { width: 100%; border-collapse: collapse; margin: 8px 0; }
.report-rich th, .report-rich td { border: 1px solid #ddd; padding: 4px 8px; font-size: 0.95em; }
.report-rich pre { background: #1e293b; color: #e2e8f0; padding: 10px 12px; border-radius: 6px; overflow-x: auto; font-size: 8.5pt; margin: 10px 0; }
.report-rich pre code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 1em; background: transparent; color: inherit; }
.report-rich :not(pre) > code { background: #f3f4f6; color: #1f2937; padding: 1px 5px; border-radius: 3px; }
.report-rich pre code[class*="language-"]::before { display: block; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; margin-bottom: 6px; content: "Código"; }
.report-rich pre code.language-json::before { content: "JSON"; }
.report-rich pre code.language-mysql::before,
.report-rich pre code.language-sql::before { content: "SQL"; }
.report-rich pre code.language-javascript::before,
.report-rich pre code.language-js::before { content: "JavaScript"; }
.report-rich pre code.language-php::before { content: "PHP"; }
.report-rich pre code.language-bash::before,
.report-rich pre code.language-shell::before { content: "Shell"; }
img.report-image { max-width: 100%; max-height: 280px; margin: 8px 0; }
{$themeCss}
{$pdfCss}
{$previewCss}
{$previewHeaderCss}
</style>
</head>
{$bodyOpen}
{$inner}
{$bodyClose}
</html>
HTML;
    }

    private function previewStyles(bool $thumbnail): string
    {
        if ($thumbnail) {
            return '
html, body.report-thumb-body { margin: 0; padding: 0; width: 100%; height: 100%; overflow: hidden; background: #e8edf3; }
.report-thumb-fit { width: 210mm; margin: 0 auto; transform-origin: top center; zoom: calc(100vw / 210mm); }
@supports not (zoom: 1) {
  .report-thumb-fit { zoom: normal; transform: scale(calc(100vw / 210mm)); }
}
.report-thumb-fit .report-page {
  width: 210mm;
  height: 297mm;
  min-height: 297mm;
  max-height: 297mm;
  margin: 0;
  padding: 48px;
  background: #fff;
  box-sizing: border-box;
  overflow: hidden;
  box-shadow: 0 2px 16px rgb(15 23 42 / 0.1);
}
.report-thumb-fit .report-page--cover .report-cover { min-height: 0 !important; height: 100%; padding: 48px; box-sizing: border-box; display: flex; flex-direction: column; justify-content: center; }
.report-thumb-fit .report-page--cover-themed { padding: 0; }
.report-thumb-fit .report-page--cover-themed .report-cover { padding: 48px; }
.report-thumb-fit .report-header,
.report-thumb-fit .report-footer { position: static; margin: 0 0 12px; border: 0; border-bottom: 1px solid #ddd; padding-bottom: 8px; }
.report-thumb-fit .report-footer { border-bottom: 0; border-top: 1px solid #ddd; padding-top: 8px; margin-top: 16px; margin-bottom: 0; }
.report-thumb-fit .meta { font-size: 9pt; color: #64748b; margin-bottom: 16px; }
';
        }

        return '
html { background: #e5e7eb; overflow-x: hidden; }
body.report-preview { margin: 0; padding: 12px 0 28px; background: #e5e7eb; overflow-x: hidden; overflow-y: auto; }
.report-preview-fit { width: 210mm; margin: 0 auto; transform-origin: top center; zoom: calc(100vw / 210mm); }
@supports not (zoom: 1) {
  .report-preview-fit { zoom: normal; transform: scale(calc(100vw / 210mm)); }
}
.report-preview-banner { background: #f59e0b; color: #1c1917; padding: 8px 12px; font-size: 10pt; margin: 0 auto 12px; max-width: 210mm; box-sizing: border-box; }
.report-page { width: 210mm; min-height: 297mm; margin: 0 auto 20px; padding: 48px; background: #fff; box-shadow: 0 4px 24px rgb(0 0 0 / 0.12); box-sizing: border-box; }
.report-page--cover-themed { padding: 0; min-height: 297mm; height: 297mm; overflow: hidden; }
.report-page--cover-sheet { padding: 0 !important; background: transparent !important; box-sizing: border-box; }
.report-page--cover-sheet .report-cover-page--sheet { width: 100%; min-height: 297mm; height: 297mm; margin: 0; }
.report-page--cover-sheet .report-cover-page__bg { position: absolute; left: 0; top: 0; right: 0; bottom: 0; }
.report-page--cover-themed .report-cover:not(.report-cover-page--sheet) { min-height: 297mm; margin: 0; box-sizing: border-box; }
.report-page--cover .report-cover { min-height: 200mm; page-break-after: always; }
';
    }

    /**
     * @param  array<string, mixed>  $component
     */
    private function renderComponent(
        array $component,
        Routine $routine,
        RoutineExecution $execution,
        ?string $companyName,
        ?string $assetTag,
        array $pageSettings,
        array $fieldContext,
    ): string {
        $typography = $pageSettings['typography'] ?? [];
        $type = $component['type'] ?? '';

        return match ($type) {
            'title' => $this->wrapBlock(
                '<h1>'.$this->formatText($this->componentText($component, $companyName ?? 'Phoenix', $assetTag ?? '', (int) ($routine->id ?? 0), 'Reporte')).'</h1>',
                $component,
                (int) ($typography['title_pt'] ?? 22),
            ),
            'subtitle' => $this->wrapBlock(
                '<h2 class="report-subtitle">'.$this->formatText($this->componentText($component, $companyName ?? 'Phoenix', $assetTag ?? '', (int) ($routine->id ?? 0), '')).'</h2>',
                $component,
                (int) ($typography['subtitle_pt'] ?? 16),
            ),
            'paragraph' => $this->wrapBlock(
                $this->renderParagraphField($component['field'] ?? '', $execution, $assetTag, $fieldContext, $component),
                $component,
                (int) ($typography['body_pt'] ?? 11),
            ),
            'text' => $this->wrapBlock(
                '<div class="report-rich">'.$this->formatText($this->componentText($component, $companyName ?? 'Phoenix', $assetTag ?? '', (int) ($routine->id ?? 0), '')).'</div>',
                $component,
                (int) ($typography['body_pt'] ?? 11),
            ),
            'image' => $this->renderImageField($component['field'] ?? '', $execution, $component, $fieldContext),
            'section_template' => $this->renderSectionTemplate($component, (int) ($routine->company_id ?? 0)),
            'divider' => $this->renderDivider($component),
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $component
     */
    /**
     * @param  array<string, mixed>  $component
     */
    private function renderSectionTemplate(array $component, int $companyId): string
    {
        $id = (int) ($component['section_template_id'] ?? 0);
        if ($id === 0 || $companyId === 0) {
            return '<p class="meta">[Sección no configurada]</p>';
        }

        $template = ReportSectionTemplate::query()
            ->where('company_id', $companyId)
            ->whereKey($id)
            ->first();

        if ($template === null) {
            return '<p class="meta">[Sección no encontrada]</p>';
        }

        $inner = '<div class="report-rich">'.$this->formatText($template->body).'</div>';

        return $this->wrapBlock($inner, $component, 11);
    }

    private function renderDivider(array $component): string
    {
        $style = ($component['style'] ?? 'solid') === 'dashed' ? 'dashed' : 'solid';
        $margin = (int) ($component['margin_pt'] ?? 16);

        return '<hr class="report-divider" style="border-top-style: '.$style.'; margin: '.$margin.'pt 0;" />';
    }

    private function wrapBlock(string $inner, array $component, int $defaultPt): string
    {
        $align = $component['align'] ?? 'left';
        $color = $component['color'] ?? null;
        $sizePt = isset($component['size_pt']) ? (int) $component['size_pt'] : $defaultPt;
        $style = 'text-align: '.$this->e($align).'; font-size: '.$sizePt.'pt;';
        if (is_string($color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $style .= ' color: '.$color.';';
        }

        return '<div style="'.$style.'">'.$inner.'</div>';
    }

    /**
     * @param  array<string, mixed>|null  $cover
     */
    private function renderCoverPage(
        ?array $cover,
        string $companyName,
        string $assetTag,
        int $routineId,
        string $font,
        int $titlePt,
        int $subtitlePt,
        array $pageSettings = [],
        ?Company $company = null,
        ?RoutineExecution $execution = null,
    ): string {
        if ($cover === null || empty($cover['enabled'])) {
            return '';
        }

        $colors = is_array($pageSettings['theme']['colors'] ?? null) ? $pageSettings['theme']['colors'] : [];
        $coverBg = is_string($colors['cover_bg'] ?? null) ? $colors['cover_bg'] : '';
        $coverText = is_string($colors['cover_text'] ?? null) ? $colors['cover_text'] : '';
        $accent = is_string($colors['accent'] ?? null) ? $colors['accent'] : '#f59e0b';

        $title = $this->replacePlaceholders((string) ($cover['title'] ?? ''), $companyName, $assetTag, $routineId);
        $subtitle = $this->replacePlaceholders((string) ($cover['subtitle'] ?? ''), $companyName, $assetTag, $routineId);
        $body = $this->formatCoverBody($this->replacePlaceholders((string) ($cover['body'] ?? ''), $companyName, $assetTag, $routineId));
        $dateLine = ! empty($cover['show_date'])
            ? '<p class="report-cover-date" style="text-align:center;'.($coverText !== '' ? 'color:'.$coverText.';' : '').'">'
                .$this->e($this->formatCoverDisplayDate($cover, $execution))
                .'</p>'
            : '';
        $imageHtml = $this->resolveCoverImageHtml($cover, $company);

        $titleStyle = 'font-size: '.$titlePt.'pt;text-align:center;width:100%;margin:0.15em 0 0.25em;';
        if ($coverText !== '') {
            $titleStyle .= 'color:'.$coverText.';';
        }

        if ($coverBg !== '') {
            return $this->renderThemedCoverSheet(
                $coverBg,
                $coverText,
                $accent,
                $font,
                $titleStyle,
                $titlePt,
                $subtitlePt,
                $title,
                $subtitle,
                $body,
                $dateLine,
                $imageHtml,
            );
        }

        $shellStyle = 'font-family: '.$font.';';

        return '<div class="report-cover report-cover--sheet report-cover--themed" style="'.$shellStyle.'">'
            .$imageHtml
            .'<div style="width:48px;height:4px;background:'.$accent.';margin:0 auto 20px;border-radius:2px;"></div>'
            .'<h1 style="'.$titleStyle.'">'.$this->formatText($title).'</h1>'
            .($subtitle !== '' ? '<h2 class="report-subtitle" style="font-size: '.$subtitlePt.'pt;border:0;text-align:center;font-weight:600;">'.$this->formatText($subtitle).'</h2>' : '')
            .($dateLine !== '' ? '<div style="text-align:center;">'.$dateLine.'</div>' : '')
            .($body !== '' ? '<div class="report-cover-body">'.$body.'</div>' : '')
            .'</div>';
    }

    private function renderThemedCoverSheet(
        string $coverBg,
        string $coverText,
        string $accent,
        string $font,
        string $titleStyle,
        int $titlePt,
        int $subtitlePt,
        string $title,
        string $subtitle,
        string $body,
        string $dateLine,
        string $imageHtml,
    ): string {
        $subtitleStyle = 'font-size: '.$subtitlePt.'pt;border:0;text-align:center;font-weight:600;margin:0.35em 0;width:100%;';
        if ($coverText !== '') {
            $subtitleStyle .= 'color:'.$coverText.';';
        }

        $heroInner = '<div class="report-cover-hero-inner" style="font-family:'.$font.';color:'.$coverText.';">'
            .$imageHtml
            .'<div style="width:48px;height:4px;background:'.$accent.';margin:12px auto 16px;border-radius:2px;"></div>'
            .'<h1 style="'.$titleStyle.'">'.$this->formatText($title).'</h1>'
            .($subtitle !== '' ? '<h2 class="report-subtitle" style="'.$subtitleStyle.'">'.$this->formatText($subtitle).'</h2>' : '')
            .$dateLine
            .($body !== '' ? '<div class="report-cover-body">'.$body.'</div>' : '')
            .'</div>';

        return '<div class="report-cover-page report-cover-page--sheet">'
            .'<div class="report-cover-page__bg" style="background:'.$coverBg.';"></div>'
            .'<div class="report-cover-page__content" style="color:'.$coverText.';">'
            .'<table class="report-cover-layout" width="100%" cellpadding="0" cellspacing="0">'
            .'<tr>'
            .'<td class="report-cover-hero-cell" align="center" valign="middle" style="color:'.$coverText.';text-align:center;">'
            .'<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="text-align:center;">'
            .$heroInner
            .'</td></tr></table>'
            .'</td>'
            .'</tr>'
            .'</table>'
            .'</div>'
            .'</div>';
    }

    /**
     * @param  array<string, mixed>  $cover
     */
    private function formatCoverDisplayDate(array $cover, ?RoutineExecution $execution): string
    {
        $fixed = trim((string) ($cover['date_fixed'] ?? ''));
        if ($fixed !== '') {
            try {
                return \Illuminate\Support\Carbon::parse($fixed)->format('d/m/Y');
            } catch (\Throwable) {
                // Usar fecha de informe si el valor guardado no es válido.
            }
        }

        $at = $execution?->submitted_at ?? $execution?->created_at;

        return ($at ?? now())->format('d/m/Y');
    }

    /**
     * @param  array<string, mixed>  $cover
     */
    private function resolveCoverImageHtml(array $cover, ?Company $company = null): string
    {
        $source = $cover['logo_source'] ?? null;
        if (! is_string($source) || $source === '') {
            if (! empty($cover['use_client_logo']) && ! empty($cover['client_id'])) {
                $source = 'client';
            } elseif (! empty($cover['image_path'])) {
                $source = 'custom';
            } else {
                $source = 'none';
            }
        }

        if ($source === 'client' && ! empty($cover['client_id'])) {
            $client = Client::query()->find((int) $cover['client_id']);
            if ($client?->logo_path) {
                return $this->renderStoredPublicImage($client->logo_path, 'report-cover-image');
            }
        }

        if ($source === 'company' && $company !== null && $company->logo_path) {
            return $this->renderStoredPublicImage($company->logo_path, 'report-cover-image');
        }

        if ($source === 'custom') {
            return $this->renderStoredPublicImage((string) ($cover['image_path'] ?? ''), 'report-cover-image');
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $component
     */
    private function componentText(array $component, string $companyName, string $assetTag, int $routineId, string $default): string
    {
        $raw = (string) ($component['text'] ?? $default);

        return $this->replacePlaceholders($raw, $companyName, $assetTag, $routineId);
    }

    private function replacePlaceholders(string $text, string $companyName, string $assetTag, int $routineId): string
    {
        return str_replace(
            ['{{company}}', '{{routine_id}}', '{{asset_tag}}'],
            [$companyName, (string) $routineId, $assetTag],
            $text,
        );
    }

    private function formatText(string $text): string
    {
        return ReportMarkdown::toHtml($text);
    }

    private function formatCoverBody(string $text): string
    {
        if ($text === '') {
            return '';
        }

        if ($this->coverBodyShouldUseMarkdown($text)) {
            $text = $this->richHtmlToPlainText($text);
        }

        return $this->alignCoverBodyHtml(ReportMarkdown::toHtml($text));
    }

    private function coverBodyShouldUseMarkdown(string $text): bool
    {
        $looksHtml = str_contains($text, '<p>') || str_contains($text, '<div>');

        if (! $looksHtml) {
            return false;
        }

        return str_contains($text, '**')
            || str_contains($text, '__')
            || str_contains($text, '\\n');
    }

    private function richHtmlToPlainText(string $html): string
    {
        $html = (string) preg_replace('#</p>\s*<p[^>]*>#i', "\n\n", $html);
        $html = (string) preg_replace('#<br\s*/?>#i', "\n", $html);

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function alignCoverBodyHtml(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $html = (string) preg_replace_callback(
            '/\sstyle="([^"]*)"/i',
            static function (array $matches): string {
                $style = (string) preg_replace(
                    '/text-align\s*:\s*(?:left|start|right|end)\s*;?/i',
                    'text-align:center;',
                    $matches[1],
                );
                $style = trim($style, '; ');

                return $style === '' ? '' : ' style="'.$style.'"';
            },
            $html,
        );

        return (string) preg_replace(
            '/<p(?![^>]*style=)([^>]*)>/i',
            '<p style="text-align:center;margin:0.35em 0;"$1>',
            $html,
        );
    }

    /**
     * @param  array{labels: array<string, string>, catalog_labels: array<string, array<string, string>>}  $fieldContext
     * @param  array<string, mixed>  $component
     */
    private function renderParagraphField(
        string $fieldKey,
        RoutineExecution $execution,
        ?string $assetTag,
        array $fieldContext,
        array $component,
    ): string {
        $rawValue = $this->fieldDisplayValue($fieldKey, $execution, $assetTag, $fieldContext);
        $displayValue = $this->formatCatalogValue($fieldKey, $rawValue, $fieldContext);
        $valueText = ($displayValue === '' || $displayValue === '—') ? '—' : $displayValue;

        $responses = $execution->responses ?? [];
        $hasResponse = is_array($responses) && array_key_exists($fieldKey, $responses)
            && $responses[$fieldKey] !== null && $responses[$fieldKey] !== '';

        if ($valueText === '—' && ! $hasResponse) {
            return '';
        }

        $humanLabel = isset($component['label']) && is_string($component['label']) && $component['label'] !== ''
            ? $component['label']
            : $this->fieldLabel($fieldKey, $fieldContext);

        $labelCell = $this->e($humanLabel).' ('.$this->e($fieldKey).')';

        return '<table width="100%" cellpadding="5" cellspacing="0" class="report-field-table" style="margin:0 0 10px 0;font-family:DejaVu Sans,sans-serif;font-size:11pt;border-collapse:collapse;">'
            .'<tr>'
            .'<td width="58%" style="font-weight:bold;vertical-align:top;border-bottom:1px solid '.$this->themeColor('border', '#e5e5e5').';">'.$labelCell.'</td>'
            .'<td style="vertical-align:top;border-bottom:1px solid '.$this->themeColor('border', '#e5e5e5').';">'.$this->e($valueText).'</td>'
            .'</tr></table>';
    }

    /**
     * @param  array{labels: array<string, string>, catalog_labels: array<string, array<string, string>>}  $fieldContext
     */
    private function fieldLabel(string $fieldKey, array $fieldContext): string
    {
        return $fieldContext['labels'][$fieldKey] ?? $fieldKey;
    }

    /**
     * @param  array{labels: array<string, string>, catalog_labels: array<string, array<string, string>>}  $fieldContext
     */
    private function formatCatalogValue(string $fieldKey, string $rawValue, array $fieldContext): string
    {
        if ($rawValue === '' || $rawValue === '—') {
            return $rawValue;
        }

        $map = $fieldContext['catalog_labels'][$fieldKey] ?? [];

        return $map[$rawValue] ?? $rawValue;
    }

    /**
     * @return array{labels: array<string, string>, catalog_labels: array<string, array<string, string>>}
     */
    private function resolveFieldContext(Routine $routine, ?int $reportTemplateId): array
    {
        $formVersion = $routine->routineType?->formVersion;
        if ($formVersion === null && $reportTemplateId !== null) {
            $formVersion = app(ReportSampleDataFactory::class)->resolveFormVersionForTemplate($reportTemplateId);
        }

        return $this->fieldContextFromFormVersion($formVersion);
    }

    /**
     * @return array{labels: array<string, string>, catalog_labels: array<string, array<string, string>>, photo_meta: array<string, array{allow_multiple: bool, max_images: int}>}
     */
    private function fieldContextFromFormVersion(?FormVersion $formVersion): array
    {
        $labels = [
            'corrected_comments' => 'Comentario corregido (IA)',
            'technician_comments' => 'Comentario técnico',
            'asset_tag' => 'Etiqueta de activo',
        ];
        $catalogLabels = [];
        $photoMeta = [];

        if ($formVersion === null) {
            return ['labels' => $labels, 'catalog_labels' => $catalogLabels, 'photo_meta' => $photoMeta];
        }

        $formVersion->loadMissing('definition');
        $companyId = (int) ($formVersion->definition?->company_id ?? 0);

        foreach ($formVersion->schema['sections'] ?? [] as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                $key = (string) ($field['key'] ?? '');
                if ($key === '') {
                    continue;
                }
                $labels[$key] = (string) ($field['label'] ?? $key);
                $type = (string) ($field['type'] ?? 'text');
                if ($type === 'photo') {
                    $allowMultiple = (bool) ($field['allow_multiple'] ?? false);
                    $photoMeta[$key] = [
                        'allow_multiple' => $allowMultiple,
                        'max_images' => $allowMultiple ? (int) ($field['max_images'] ?? 4) : 1,
                    ];
                }
                $catalogId = $field['option_catalog_id'] ?? null;
                if ($catalogId !== null) {
                    $catalog = FormOptionCatalog::query()
                        ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
                        ->whereKey($catalogId)
                        ->first();
                    if ($catalog !== null) {
                        $map = [];
                        foreach ($catalog->options ?? [] as $option) {
                            $value = (string) ($option['value'] ?? '');
                            if ($value !== '') {
                                $map[$value] = (string) ($option['label'] ?? $value);
                            }
                        }
                        $catalogLabels[$key] = $map;
                    }
                }
            }
        }

        return ['labels' => $labels, 'catalog_labels' => $catalogLabels, 'photo_meta' => $photoMeta];
    }

    /**
     * @param  array{labels: array<string, string>, catalog_labels: array<string, array<string, string>>, photo_meta: array<string, array{allow_multiple: bool, max_images: int}>}  $fieldContext
     */
    private function renderImageField(string $field, RoutineExecution $execution, array $component = [], array $fieldContext = []): string
    {
        $responses = $execution->responses ?? [];
        $raw = is_array($responses) ? ($responses[$field] ?? null) : null;
        $items = PhotoResponseNormalizer::toItems($raw);

        if ($items === []) {
            $path = $this->fieldDisplayValue($field, $execution, null);
            if ($path === '' || $path === '—') {
                $label = $this->fieldLabel($field, $fieldContext);

                return '<p class="meta">[Sin imagen: '.$this->e($label).']</p>';
            }
            $items = [['path' => $path]];
        }

        $label = $this->fieldLabel($field, $fieldContext);
        $hint = $this->photoFieldHint($field, $fieldContext);

        $html = '<p class="report-field-label"><strong>'.$this->e($label).'</strong></p>';
        if ($hint !== '') {
            $html .= '<p class="report-field-hint">'.$this->e($hint).'</p>';
        }
        $html .= '<div class="report-gallery">';
        foreach ($items as $item) {
            $html .= $this->renderSingleImage($item['path'], $item['caption'] ?? null);
        }
        $html .= '</div>';

        return $this->wrapBlock($html, $component, 11);
    }

    /**
     * @param  array{photo_meta?: array<string, array{allow_multiple: bool, max_images: int}>}  $fieldContext
     */
    private function photoFieldHint(string $fieldKey, array $fieldContext): string
    {
        $meta = $fieldContext['photo_meta'][$fieldKey] ?? null;
        if ($meta === null) {
            return '';
        }
        if (! empty($meta['allow_multiple'])) {
            $max = max(1, (int) ($meta['max_images'] ?? 4));

            return 'Hasta '.$max.' imágenes permitidas en este campo.';
        }

        return 'Una imagen por campo.';
    }

    private function renderSingleImage(string $path, ?string $caption): string
    {
        if ($path === '__preview_placeholder__') {
            return '<div style="background:#e5e7eb;height:120px;display:flex;align-items:center;justify-content:center;color:#6b7280;font-size:10pt;">Imagen de ejemplo</div>'
                .($caption ? '<p class="report-image-caption">'.e($caption).'</p>' : '');
        }

        $diskName = config('phoenix.evidence.disk', 'evidence');
        $disk = Storage::disk($diskName);
        if (! $disk->exists($path)) {
            return '<p class="meta">[Imagen no disponible]</p>';
        }

        $mime = $disk->mimeType($path) ?: 'image/jpeg';
        $binary = $disk->get($path);
        $src = ReportPdfImageEncoder::toEmbeddedSrc($binary, $mime);
        if ($src === null) {
            return '<p class="meta">[Imagen no compatible para PDF — use JPEG o PNG]</p>';
        }
        $img = '<img class="report-image" src="'.$src.'" alt="" />';

        if ($caption !== null && $caption !== '') {
            $img .= '<p class="report-image-caption">'.e($caption).'</p>';
        }

        return $img;
    }

    private function renderStoredPublicImage(string $path, string $class): string
    {
        if ($path === '') {
            return '';
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return '';
        }

        $mime = $disk->mimeType($path) ?: 'image/jpeg';
        $binary = $disk->get($path);
        $src = ReportPdfImageEncoder::toEmbeddedSrc($binary, $mime);
        if ($src === null) {
            return '';
        }

        return '<img class="'.$this->e($class).'" src="'.$src.'" alt="" />';
    }

    private function fieldDisplayValue(string $field, RoutineExecution $execution, ?string $assetTag, array $fieldContext = []): string
    {
        $responses = $execution->responses ?? [];
        if (is_array($responses) && array_key_exists($field, $responses)) {
            $raw = $responses[$field];
            if ($raw === null || $raw === '') {
                return '';
            }
            if (is_scalar($raw)) {
                return (string) $raw;
            }
            if (is_array($raw)) {
                if (isset($raw['path'])) {
                    return (string) ($raw['caption'] ?? $raw['path']);
                }
                $items = PhotoResponseNormalizer::toItems($raw);
                if ($items !== []) {
                    return implode(', ', array_map(fn ($i) => $i['caption'] ?? $i['path'], $items));
                }
            }
        }

        $fallback = match ($field) {
            'corrected_comments' => $execution->corrected_comments ?? $execution->technician_comments ?? '',
            'technician_comments' => $execution->technician_comments ?? '',
            'asset_tag' => $assetTag ?? '',
            default => '',
        };

        return $fallback !== '' ? $fallback : '—';
    }

    private function renderHeaderFooter(?array $block, string $companyName, int $routineId, string $position, string $assetTag = '', bool $isPreview = false): string
    {
        if ($block === null || empty($block['enabled'])) {
            return '';
        }

        $text = str_replace(
            ['{{company}}', '{{routine_id}}', '{{asset_tag}}'],
            [$companyName, (string) $routineId, $assetTag],
            (string) ($block['text'] ?? ''),
        );

        $class = $position === 'footer' ? 'report-footer' : 'report-header';

        return '<div class="'.$class.'">'.$this->formatText($text).'</div>';
    }

    private function fontFamily(string $key): string
    {
        return match ($key) {
            'source_sans' => "'Source Sans 3', 'DejaVu Sans', sans-serif",
            default => "'Roboto', 'DejaVu Sans', sans-serif",
        };
    }

    private function fieldValue(string $field, RoutineExecution $execution, ?string $assetTag): string
    {
        return $this->fieldDisplayValue($field, $execution, $assetTag);
    }

    private function e(?string $value): string
    {
        return e($value ?? '');
    }

    private function themeColor(string $key, string $default): string
    {
        $colors = is_array($this->documentTheme['colors'] ?? null) ? $this->documentTheme['colors'] : [];
        $value = $colors[$key] ?? $default;

        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? $value : $default;
    }

    /**
     * @param  array<string, mixed>  $pageSettings
     */
    private function themedCoverPageOpeningTag(array $pageSettings): string
    {
        $colors = is_array($pageSettings['theme']['colors'] ?? null) ? $pageSettings['theme']['colors'] : [];
        $coverBg = is_string($colors['cover_bg'] ?? null) ? $colors['cover_bg'] : '';
        if ($coverBg === '' || ! preg_match('/^#[0-9A-Fa-f]{6}$/', $coverBg)) {
            return '<div class="report-page report-page--cover">';
        }

        return '<div class="report-page report-page--cover report-page--cover-themed report-page--cover-sheet" style="background:'.$coverBg.';padding:0;">';
    }

    private function hasThemedCoverBackground(): bool
    {
        $colors = is_array($this->documentTheme['colors'] ?? null) ? $this->documentTheme['colors'] : [];
        $coverBg = $colors['cover_bg'] ?? null;

        return is_string($coverBg) && preg_match('/^#[0-9A-Fa-f]{6}$/', $coverBg);
    }

    /**
     * @param  array<string, mixed>  $coverSettings
     */
    private function coverOmitsHeaderFooter(array $coverSettings): bool
    {
        if (! array_key_exists('omit_header_footer', $coverSettings)) {
            return true;
        }

        $value = $coverSettings['omit_header_footer'];
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }
        if (is_string($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            return $parsed ?? true;
        }

        return (bool) $value;
    }

    private function themeStyles(): string
    {
        if ($this->documentTheme === null) {
            return '';
        }

        $primary = $this->themeColor('primary', '#d97706');
        $accent = $this->themeColor('accent', '#f59e0b');
        $headerBg = $this->themeColor('header_bg', '#f8fafc');

        return <<<CSS
.report-preview-banner { background: {$accent}; color: #1c1917; }
.report-header, .report-footer { color: {$this->themeColor('muted', '#475569')}; }
.report-rich th { background: {$headerBg}; color: {$this->themeColor('secondary', '#0f172a')}; }
.report-field-table td:first-child { color: {$this->themeColor('secondary', '#0f172a')}; }
CSS;
    }
}
