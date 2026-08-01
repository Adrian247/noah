<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use iio\libmergepdf\Merger;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Spatie\Browsershot\Browsershot;
use Throwable;

/**
 * Renderiza HTML de informes a PDF (ADR-005: Chromium / Browsershot; DomPDF como fallback).
 */
class ReportPdfRenderer
{
    public function driver(): string
    {
        $configured = strtolower((string) config('phoenix.reports.pdf_driver', 'auto'));

        if (in_array($configured, ['browsershot', 'chromium', 'chrome'], true)) {
            return $this->browsershotAvailable() ? 'browsershot' : 'dompdf';
        }

        if ($configured === 'dompdf') {
            return 'dompdf';
        }

        return $this->browsershotAvailable() ? 'browsershot' : 'dompdf';
    }

    public function browsershotAvailable(): bool
    {
        $chrome = (string) config('phoenix.reports.chrome_path', '');
        if ($chrome !== '' && is_executable($chrome)) {
            return true;
        }

        foreach (['/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome', '/usr/bin/google-chrome-stable'] as $path) {
            if (is_executable($path)) {
                return true;
            }
        }

        return false;
    }

    public function htmlToPdf(string $html): string
    {
        return $this->renderDocument(new ReportPdfDocument($html));
    }

    public function renderDocument(ReportPdfDocument $document): string
    {
        if ($this->driver() === 'browsershot') {
            try {
                return $this->renderWithBrowsershot($document);
            } catch (Throwable $e) {
                Log::warning('Browsershot PDF falló; usando DomPDF.', [
                    'message' => $e->getMessage(),
                ]);
                if (! (bool) config('phoenix.reports.pdf_fallback_dompdf', true)) {
                    throw $e;
                }
            }
        }

        return $this->renderWithDompdf($document);
    }

    private function renderWithDompdf(ReportPdfDocument $document): string
    {
        // Siempre el HTML completo (incluye portada + cuerpo).
        $html = $document->html;
        $enablePhp = str_contains($html, 'type="text/php"');
        $pdf = Pdf::loadHTML($html)->setPaper('a4');
        $pdf->getDomPDF()->set_option('isPhpEnabled', $enablePhp);
        $pdf->getDomPDF()->set_option('isRemoteEnabled', false);

        return $pdf->output();
    }

    private function renderWithBrowsershot(ReportPdfDocument $document): string
    {
        if ($document->hasSeparateCover()) {
            $coverPdf = $this->browsershotPdf(
                (string) $document->coverHtml,
                withChrome: false,
                coverSheet: true,
            );
            $bodyPdf = $this->browsershotPdf(
                (string) $document->bodyHtml,
                withChrome: true,
                document: $document,
            );

            // Evitar páginas fantasma de portada (bleed/overflow).
            $coverPdf = $this->keepFirstPdfPageOnly($coverPdf);

            $merger = new Merger;
            $merger->addRaw($coverPdf);
            $merger->addRaw($bodyPdf);

            return $merger->merge();
        }

        return $this->browsershotPdf($document->html, withChrome: true, document: $document);
    }

    private function browsershotPdf(
        string $html,
        bool $withChrome,
        ?ReportPdfDocument $document = null,
        bool $coverSheet = false,
    ): string {
        $shot = Browsershot::html($html)
            ->format('A4')
            ->showBackground()
            ->timeout((int) config('phoenix.reports.browsershot_timeout', 60))
            ->setOption('args', ['--disable-dev-shm-usage']);

        if ($coverSheet) {
            // Portada a sangre: sin márgenes de página (el CSS fija 210×297mm).
            $shot->margins(0, 0, 0, 0);
        } else {
            $shot->margins(18, 14, 22, 14);
        }

        if ((bool) config('phoenix.reports.chrome_no_sandbox', true)) {
            $shot->noSandbox();
        }

        $chromePath = $this->resolveChromePath();
        if ($chromePath !== null) {
            $shot->setChromePath($chromePath);
        }

        $node = (string) config('phoenix.reports.node_binary', '');
        if ($node !== '' && is_executable($node)) {
            $shot->setNodeBinary($node);
        }

        $npm = (string) config('phoenix.reports.npm_binary', '');
        if ($npm !== '' && is_executable($npm)) {
            $shot->setNpmBinary($npm);
        }

        if ($withChrome && ! $coverSheet && $document !== null && $this->documentNeedsChrome($document)) {
            $shot->showBrowserHeaderAndFooter();
            $shot->headerHtml($this->chromeHeaderTemplate($document));
            $shot->footerHtml($this->chromeFooterTemplate($document));
            $shot->margins(22, 14, 24, 14);
        }

        $pdf = $shot->pdf();
        if (! is_string($pdf) || $pdf === '') {
            throw new RuntimeException('Browsershot devolvió PDF vacío.');
        }

        return $pdf;
    }

    /**
     * Si la portada Chromium genera >1 página por overflow, conserva solo la primera.
     */
    private function keepFirstPdfPageOnly(string $pdf): string
    {
        try {
            $merger = new Merger;
            $merger->addRaw($pdf, [1]);

            return $merger->merge();
        } catch (Throwable) {
            return $pdf;
        }
    }

    private function documentNeedsChrome(ReportPdfDocument $document): bool
    {
        return $document->headerText() !== ''
            || $document->footerText() !== ''
            || $document->pageNumbersEnabled();
    }

    private function chromeHeaderTemplate(ReportPdfDocument $document): string
    {
        $text = e($document->headerText());
        if ($text === '') {
            return '<div></div>';
        }

        return '<div style="font-size:9px;width:100%;padding:0 14mm;color:#555;font-family:DejaVu Sans,sans-serif;">'
            .$text
            .'</div>';
    }

    private function chromeFooterTemplate(ReportPdfDocument $document): string
    {
        $parts = [];
        $footer = e($document->footerText());
        if ($footer !== '') {
            $parts[] = '<span>'.$footer.'</span>';
        }
        if ($document->pageNumbersEnabled()) {
            $parts[] = '<span>Página <span class="pageNumber"></span> de <span class="totalPages"></span></span>';
        }
        if ($parts === []) {
            return '<div></div>';
        }

        return '<div style="font-size:9px;width:100%;padding:0 14mm;color:#666;font-family:DejaVu Sans,sans-serif;">'
            .implode('&nbsp;&nbsp;', $parts)
            .'</div>';
    }

    private function resolveChromePath(): ?string
    {
        $configured = (string) config('phoenix.reports.chrome_path', '');
        if ($configured !== '' && is_executable($configured)) {
            return $configured;
        }

        foreach (['/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome', '/usr/bin/google-chrome-stable'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return null;
    }
}
