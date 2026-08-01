<?php

namespace App\Services\Reports;

/**
 * Placeholders compartidos entre cuerpo y portada del informe.
 */
final class ReportPlaceholders
{
    public static function replace(string $text, string $companyName, string $assetTag, int $routineId): string
    {
        // {{page}} no se sustituye en texto: DomPDF numera aparte. Se elimina para no filtrar literales.
        $text = (string) preg_replace('/\s*·?\s*Página\s*\{\{page\}\}/iu', '', $text);
        $text = str_replace('{{page}}', '', $text);

        return str_replace(
            ['{{company}}', '{{routine_id}}', '{{asset_tag}}'],
            [$companyName, (string) $routineId, $assetTag],
            $text,
        );
    }
}
