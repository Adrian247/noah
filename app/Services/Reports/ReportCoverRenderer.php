<?php

namespace App\Services\Reports;

use App\Models\Client;
use App\Models\Company;
use App\Models\RoutineExecution;
use Illuminate\Support\Carbon;

/**
 * Renderizado aislado de la portada PDF/HTML.
 * Mantener cambios de portada aquí evita romper el cuerpo del informe.
 */
final class ReportCoverRenderer
{
    /**
     * @param  array<string, mixed>|null  $cover
     * @param  array<string, mixed>  $pageSettings
     */
    public function render(
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

        $title = ReportPlaceholders::replace((string) ($cover['title'] ?? ''), $companyName, $assetTag, $routineId);
        $subtitle = ReportPlaceholders::replace((string) ($cover['subtitle'] ?? ''), $companyName, $assetTag, $routineId);
        $body = $this->formatCoverBody(
            ReportPlaceholders::replace((string) ($cover['body'] ?? ''), $companyName, $assetTag, $routineId),
        );
        $dateLine = ! empty($cover['show_date'])
            ? '<p class="report-cover-date" style="text-align:center;'.($coverText !== '' ? 'color:'.$coverText.';' : '').'">'
                .e($this->formatCoverDisplayDate($cover, $execution))
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
            .'<h1 style="'.$titleStyle.'">'.ReportMarkdown::toHtml($title).'</h1>'
            .($subtitle !== '' ? '<h2 class="report-subtitle" style="font-size: '.$subtitlePt.'pt;border:0;text-align:center;font-weight:600;">'.ReportMarkdown::toHtml($subtitle).'</h2>' : '')
            .($dateLine !== '' ? '<div style="text-align:center;">'.$dateLine.'</div>' : '')
            .($body !== '' ? '<div class="report-cover-body">'.$body.'</div>' : '')
            .'</div>';
    }

    /**
     * @param  array<string, mixed>  $pageSettings
     */
    public function themedCoverPageOpeningTag(array $pageSettings): string
    {
        $colors = is_array($pageSettings['theme']['colors'] ?? null) ? $pageSettings['theme']['colors'] : [];
        $coverBg = is_string($colors['cover_bg'] ?? null) ? $colors['cover_bg'] : '';
        if ($coverBg === '' || ! preg_match('/^#[0-9A-Fa-f]{6}$/', $coverBg)) {
            return '<div class="report-page report-page--cover">';
        }

        return '<div class="report-page report-page--cover report-page--cover-themed report-page--cover-sheet" style="background:'.$coverBg.';padding:0;">';
    }

    /**
     * @param  array<string, mixed>|null  $theme
     */
    public function hasThemedCoverBackground(?array $theme): bool
    {
        $colors = is_array($theme['colors'] ?? null) ? $theme['colors'] : [];
        $coverBg = $colors['cover_bg'] ?? null;

        return is_string($coverBg) && preg_match('/^#[0-9A-Fa-f]{6}$/', $coverBg);
    }

    /**
     * Contrato: con portada activa siempre se omite H/F en hoja 1.
     *
     * @param  array<string, mixed>  $coverSettings
     */
    public function omitsHeaderFooter(array $coverSettings): bool
    {
        if (! empty($coverSettings['enabled'])) {
            return true;
        }

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

    private function renderThemedCoverSheet(
        string $coverBg,
        string $coverText,
        string $accent,
        string $font,
        string $titleStyle,
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
            .'<h1 style="'.$titleStyle.'">'.ReportMarkdown::toHtml($title).'</h1>'
            .($subtitle !== '' ? '<h2 class="report-subtitle" style="'.$subtitleStyle.'">'.ReportMarkdown::toHtml($subtitle).'</h2>' : '')
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
                return Carbon::parse($fixed)->format('d/m/Y');
            } catch (\Throwable) {
                // fecha de informe
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
                return ReportPublicImageEmbedder::imgTag($client->logo_path, 'report-cover-image');
            }
        }

        if ($source === 'company' && $company !== null && $company->logo_path) {
            return ReportPublicImageEmbedder::imgTag($company->logo_path, 'report-cover-image');
        }

        if ($source === 'custom') {
            return ReportPublicImageEmbedder::imgTag((string) ($cover['image_path'] ?? ''), 'report-cover-image');
        }

        return '';
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
}
