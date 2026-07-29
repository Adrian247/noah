<?php

namespace App\Services\Reports;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;

class ReportMarkdown
{
    private static ?MarkdownConverter $converter = null;

    public static function toHtml(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $text = self::normalizeTextEscapes($text);

        if (self::looksLikeHtml($text)) {
            return self::sanitizeRichHtml($text);
        }

        if (self::looksLikeLegacyInline($text)) {
            return self::legacyToHtml($text);
        }

        return self::converter()->convert($text)->getContent();
    }

    private static function normalizeTextEscapes(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Cadenas guardadas desde JSON/UI con saltos escapados como texto literal.
        return str_replace(['\\r\\n', '\\n', '\\r'], "\n", $text);
    }

    private static function looksLikeHtml(string $text): bool
    {
        return str_contains($text, '<p>') || str_contains($text, '<h') || str_contains($text, '<table')
            || str_contains($text, '<pre') || str_contains($text, '<ul');
    }

    private static function sanitizeRichHtml(string $html): string
    {
        $html = (string) preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
        $html = (string) preg_replace('#<iframe\b[^>]*>.*?</iframe>#is', '', $html);
        $html = (string) preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\')#i', '', $html);

        return $html;
    }

    private static function looksLikeLegacyInline(string $text): bool
    {
        if (str_contains($text, '#') || str_contains($text, '- ') || str_contains($text, '```')) {
            return false;
        }

        return str_contains($text, '**') || str_contains($text, '__');
    }

    private static function legacyToHtml(string $text): string
    {
        $escaped = e($text);
        $escaped = (string) preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped);
        $escaped = (string) preg_replace('/__(.+?)__/s', '<u>$1</u>', $escaped);

        return nl2br($escaped);
    }

    private static function converter(): MarkdownConverter
    {
        if (self::$converter === null) {
            $environment = new Environment([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
            $environment->addExtension(new CommonMarkCoreExtension);
            self::$converter = new MarkdownConverter($environment);
        }

        return self::$converter;
    }
}
