<?php

namespace App\Support\Spreadsheet;

use ZipArchive;

/**
 * Lector mínimo de la primera hoja de un archivo .xlsx (sin dependencias externas).
 */
final class SimpleXlsxReader
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /**
     * @return list<array<string, string>>
     */
    public static function sheetToAssocRows(string $path, int $sheetIndex = 0): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \InvalidArgumentException("No se pudo abrir el archivo Excel: {$path}");
        }

        try {
            $sharedStrings = self::readSharedStrings($zip);
            $sheetXml = self::readSheetXml($zip, $sheetIndex);
        } finally {
            $zip->close();
        }

        $matrix = self::parseSheetMatrix($sheetXml, $sharedStrings);
        if ($matrix === []) {
            return [];
        }

        $headers = array_map(static fn ($header) => trim((string) $header), $matrix[0]);
        $rows = [];

        for ($rowIndex = 1, $rowCount = count($matrix); $rowIndex < $rowCount; $rowIndex++) {
            $assoc = [];
            $hasData = false;

            foreach ($headers as $columnIndex => $header) {
                if ($header === '') {
                    continue;
                }

                $value = trim((string) ($matrix[$rowIndex][$columnIndex] ?? ''));
                if ($value !== '') {
                    $hasData = true;
                }

                $assoc[$header] = $value;
            }

            if ($hasData) {
                $rows[] = $assoc;
            }
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private static function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $document = simplexml_load_string($xml);
        if ($document === false) {
            return [];
        }

        $document->registerXPathNamespace('m', self::NS);
        $strings = [];

        foreach ($document->xpath('//m:si') ?: [] as $sharedItem) {
            $sharedItem->registerXPathNamespace('m', self::NS);
            $parts = $sharedItem->xpath('.//m:t') ?: [];
            $strings[] = implode('', array_map(static fn ($node) => (string) $node, $parts));
        }

        return $strings;
    }

    private static function readSheetXml(ZipArchive $zip, int $sheetIndex): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        if ($workbookXml === false) {
            throw new \RuntimeException('El archivo Excel no contiene workbook.xml.');
        }

        $workbook = simplexml_load_string($workbookXml);
        if ($workbook === false) {
            throw new \RuntimeException('No se pudo interpretar workbook.xml.');
        }

        $workbook->registerXPathNamespace('m', self::NS);
        $sheetNodes = $workbook->xpath('//m:sheets/m:sheet') ?: [];
        if (! isset($sheetNodes[$sheetIndex])) {
            throw new \RuntimeException("La hoja índice {$sheetIndex} no existe en el archivo Excel.");
        }

        $relNs = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $relationshipId = (string) ($sheetNodes[$sheetIndex]->attributes($relNs)->id ?? '');
        if ($relationshipId === '') {
            throw new \RuntimeException('No se encontró la relación de la hoja solicitada.');
        }

        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($relsXml === false) {
            throw new \RuntimeException('El archivo Excel no contiene relaciones del workbook.');
        }

        $rels = simplexml_load_string($relsXml);
        if ($rels === false) {
            throw new \RuntimeException('No se pudieron interpretar las relaciones del workbook.');
        }

        $rels->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $target = null;

        foreach ($rels->xpath('//r:Relationship') ?: [] as $relationship) {
            if ((string) ($relationship['Id'] ?? '') === $relationshipId) {
                $target = (string) ($relationship['Target'] ?? '');
                break;
            }
        }

        if ($target === null || $target === '') {
            throw new \RuntimeException('No se encontró el destino de la hoja solicitada.');
        }

        $sheetPath = str_starts_with($target, '/')
            ? ltrim($target, '/')
            : 'xl/'.ltrim($target, '/');

        $sheetXml = $zip->getFromName($sheetPath);
        if ($sheetXml === false) {
            throw new \RuntimeException("No se pudo leer la hoja: {$sheetPath}");
        }

        return $sheetXml;
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return list<list<string>>
     */
    private static function parseSheetMatrix(string $sheetXml, array $sharedStrings): array
    {
        $document = simplexml_load_string($sheetXml);
        if ($document === false) {
            return [];
        }

        $document->registerXPathNamespace('m', self::NS);
        $matrix = [];
        $maxColumn = -1;

        foreach ($document->xpath('//m:sheetData/m:row') ?: [] as $rowNode) {
            $rowNode->registerXPathNamespace('m', self::NS);
            $rowIndex = max(0, ((int) ($rowNode['r'] ?? 0)) - 1);
            $matrix[$rowIndex] ??= [];

            foreach ($rowNode->xpath('m:c') ?: [] as $cellNode) {
                $columnIndex = self::columnIndexFromReference((string) ($cellNode['r'] ?? ''));
                if ($columnIndex < 0) {
                    continue;
                }

                $matrix[$rowIndex][$columnIndex] = self::cellValue($cellNode, $sharedStrings);
                $maxColumn = max($maxColumn, $columnIndex);
            }
        }

        if ($matrix === []) {
            return [];
        }

        ksort($matrix);
        $normalized = [];

        foreach ($matrix as $row) {
            $normalizedRow = [];
            for ($column = 0; $column <= $maxColumn; $column++) {
                $normalizedRow[] = (string) ($row[$column] ?? '');
            }
            $normalized[] = $normalizedRow;
        }

        return $normalized;
    }

    /**
     * @param  list<string>  $sharedStrings
     */
    private static function cellValue(\SimpleXMLElement $cellNode, array $sharedStrings): string
    {
        $type = (string) ($cellNode['t'] ?? '');

        if ($type === 'inlineStr') {
            $cellNode->registerXPathNamespace('m', self::NS);
            $parts = $cellNode->xpath('.//m:t') ?: [];

            return implode('', array_map(static fn ($node) => (string) $node, $parts));
        }

        $valueNode = $cellNode->children(self::NS)->v ?? null;
        if ($valueNode === null) {
            return '';
        }

        $raw = (string) $valueNode;

        if ($type === 's') {
            return $sharedStrings[(int) $raw] ?? '';
        }

        return $raw;
    }

    private static function columnIndexFromReference(string $reference): int
    {
        if (! preg_match('/^([A-Z]+)/', strtoupper($reference), $matches)) {
            return -1;
        }

        $letters = $matches[1];
        $index = 0;

        for ($offset = 0, $length = strlen($letters); $offset < $length; $offset++) {
            $index = ($index * 26) + (ord($letters[$offset]) - 64);
        }

        return $index - 1;
    }
}
