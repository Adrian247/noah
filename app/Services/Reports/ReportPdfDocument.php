<?php

namespace App\Services\Reports;

/**
 * Documento PDF listo para renderizar (Chromium o DomPDF).
 *
 * - html: siempre el documento completo (fallback DomPDF / inspección).
 * - coverHtml + bodyHtml: hoja de portada y cuerpo separados (Browsershot).
 */
final class ReportPdfDocument
{
    /**
     * @param  array{
     *   header?: string,
     *   footer?: string,
     *   page_numbers?: bool,
     *   page_number_start_at?: int,
     *   skip_first_page_chrome?: bool,
     * }  $chrome
     */
    public function __construct(
        public readonly string $html,
        public readonly ?string $coverHtml = null,
        public readonly ?string $bodyHtml = null,
        public readonly array $chrome = [],
    ) {}

    public function hasSeparateCover(): bool
    {
        return is_string($this->coverHtml) && $this->coverHtml !== ''
            && is_string($this->bodyHtml) && $this->bodyHtml !== '';
    }

    public function headerText(): string
    {
        return (string) ($this->chrome['header'] ?? '');
    }

    public function footerText(): string
    {
        return (string) ($this->chrome['footer'] ?? '');
    }

    public function pageNumbersEnabled(): bool
    {
        return (bool) ($this->chrome['page_numbers'] ?? false);
    }

    public function pageNumberStartAt(): int
    {
        return max(1, (int) ($this->chrome['page_number_start_at'] ?? 1));
    }

    public function skipFirstPageChrome(): bool
    {
        return (bool) ($this->chrome['skip_first_page_chrome'] ?? false);
    }
}
