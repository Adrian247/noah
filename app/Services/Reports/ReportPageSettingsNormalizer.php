<?php

namespace App\Services\Reports;

/**
 * Normaliza y congela el contrato de page_settings (portada estable).
 *
 * Contrato v1 de portada:
 * - Si cover_page.enabled: omit_header_footer = true siempre (hoja 1 sin chrome DomPDF).
 * - Numeración: start_at por defecto 2 cuando hay portada.
 * - Colores hex #RRGGBB; logo_source controlado; {{page}} eliminado de textos H/F.
 */
final class ReportPageSettingsNormalizer
{
    public const SCHEMA_VERSION = 1;

    /**
     * @param  array<string, mixed>  $pageSettings
     * @return array<string, mixed>
     */
    public function normalize(array $pageSettings): array
    {
        $out = $pageSettings;
        $out['schema_version'] = self::SCHEMA_VERSION;
        $out['size'] = is_string($out['size'] ?? null) && $out['size'] !== '' ? $out['size'] : 'A4';

        if (isset($out['font_family']) && ! is_string($out['font_family'])) {
            unset($out['font_family']);
        }

        $out['typography'] = $this->normalizeTypography(
            is_array($out['typography'] ?? null) ? $out['typography'] : [],
        );
        $out['theme'] = $this->normalizeTheme(
            is_array($out['theme'] ?? null) ? $out['theme'] : [],
        );
        $out['header'] = $this->normalizeChromeBlock(
            is_array($out['header'] ?? null) ? $out['header'] : [],
        );
        $out['footer'] = $this->normalizeChromeBlock(
            is_array($out['footer'] ?? null) ? $out['footer'] : [],
        );
        $out['cover_page'] = $this->normalizeCover(
            is_array($out['cover_page'] ?? null) ? $out['cover_page'] : [],
        );

        $coverEnabled = ! empty($out['cover_page']['enabled']);
        $out['page_number'] = $this->normalizePageNumber(
            is_array($out['page_number'] ?? null) ? $out['page_number'] : [],
            $coverEnabled,
        );

        return $out;
    }

    /**
     * @param  array<string, mixed>  $typography
     * @return array<string, mixed>
     */
    private function normalizeTypography(array $typography): array
    {
        foreach (['title_pt', 'subtitle_pt', 'body_pt'] as $key) {
            if (isset($typography[$key]) && is_numeric($typography[$key])) {
                $typography[$key] = max(6, min(72, (int) $typography[$key]));
            }
        }

        return $typography;
    }

    /**
     * @param  array<string, mixed>  $theme
     * @return array<string, mixed>
     */
    private function normalizeTheme(array $theme): array
    {
        $style = (string) ($theme['section_style'] ?? 'minimal');
        $theme['section_style'] = in_array($style, ['card', 'line', 'minimal'], true) ? $style : 'minimal';

        $colors = is_array($theme['colors'] ?? null) ? $theme['colors'] : [];
        foreach ($colors as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                unset($colors[$key]);

                continue;
            }
            $hex = $this->normalizeHex($value);
            if ($hex === null) {
                unset($colors[$key]);
            } else {
                $colors[$key] = $hex;
            }
        }
        $theme['colors'] = $colors;

        return $theme;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function normalizeChromeBlock(array $block): array
    {
        if (array_key_exists('enabled', $block)) {
            $block['enabled'] = (bool) $block['enabled'];
        }
        if (isset($block['text']) && is_string($block['text'])) {
            $block['text'] = $this->stripPageToken($block['text']);
        }

        return $block;
    }

    private function stripPageToken(string $text): string
    {
        $text = (string) preg_replace('/\s*·?\s*Página\s*\{\{page\}\}/iu', '', $text);

        return str_replace('{{page}}', '', $text);
    }

    /**
     * @param  array<string, mixed>  $cover
     * @return array<string, mixed>
     */
    private function normalizeCover(array $cover): array
    {
        $enabled = ! empty($cover['enabled']);
        $cover['enabled'] = $enabled;

        // Contrato congelado: portada nunca lleva encabezado/pie DomPDF.
        if ($enabled) {
            $cover['omit_header_footer'] = true;
        } elseif (! array_key_exists('omit_header_footer', $cover)) {
            $cover['omit_header_footer'] = true;
        } else {
            $cover['omit_header_footer'] = (bool) $cover['omit_header_footer'];
        }

        foreach (['title', 'subtitle', 'body', 'date_fixed', 'image_path'] as $key) {
            if (isset($cover[$key]) && is_string($cover[$key])) {
                if (in_array($key, ['title', 'subtitle', 'body'], true)) {
                    $cover[$key] = $this->stripPageToken($cover[$key]);
                } else {
                    $cover[$key] = $cover[$key];
                }
            }
        }

        if (array_key_exists('show_date', $cover)) {
            $cover['show_date'] = (bool) $cover['show_date'];
        }

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
        if (! in_array($source, ['none', 'company', 'client', 'custom'], true)) {
            $source = 'none';
        }
        $cover['logo_source'] = $source;

        if ($source === 'client' && ! empty($cover['client_id'])) {
            $cover['client_id'] = (int) $cover['client_id'];
        } elseif ($source !== 'client') {
            unset($cover['client_id']);
        }

        if ($source !== 'custom') {
            // Conservar image_path por si vuelven a custom; no borrar archivo aquí.
        }

        $cover['use_client_logo'] = $source === 'client';

        return $cover;
    }

    /**
     * @param  array<string, mixed>  $pageNumber
     * @return array<string, mixed>
     */
    private function normalizePageNumber(array $pageNumber, bool $coverEnabled): array
    {
        if (array_key_exists('enabled', $pageNumber)) {
            $pageNumber['enabled'] = (bool) $pageNumber['enabled'];
        }
        $startAt = isset($pageNumber['start_at']) && is_numeric($pageNumber['start_at'])
            ? (int) $pageNumber['start_at']
            : ($coverEnabled ? 2 : 1);
        $pageNumber['start_at'] = max(1, min(99, $startAt));

        return $pageNumber;
    }

    private function normalizeHex(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^#([0-9A-Fa-f]{3})$/', $value, $m) === 1) {
            $h = strtolower($m[1]);

            return '#'.$h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
        }
        if (preg_match('/^#([0-9A-Fa-f]{6})$/', $value) === 1) {
            return strtolower($value);
        }

        return null;
    }
}
