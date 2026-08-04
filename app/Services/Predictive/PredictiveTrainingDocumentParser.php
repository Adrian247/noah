<?php

namespace App\Services\Predictive;

use App\Enums\PredictiveAlgorithmKind;
use InvalidArgumentException;

/**
 * Contrato de documentos de entrenamiento: phoenix.predictive.training/v1
 *
 * JSON:
 * {
 *   "contract": "phoenix.predictive.training/v1",
 *   "kind": "maintenance_hazard_v2|manufacturing_demand_v1|inventory_demand_v1",
 *   "records": [ ... ]
 * }
 *
 * CSV: cabeceras según kind (ver expectedCsvHeaders).
 */
final class PredictiveTrainingDocumentParser
{
    public const CONTRACT = 'phoenix.predictive.training/v1';

    /**
     * @return array{kind: string, records: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function parse(string $contents, string $filename, ?string $forcedKind = null): array
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, ['json', ''], true) || str_starts_with(ltrim($contents), '{')) {
            return $this->parseJson($contents, $forcedKind);
        }
        if ($ext === 'csv' || str_contains($contents, ',')) {
            return $this->parseCsv($contents, $forcedKind);
        }

        throw new InvalidArgumentException('Formato no soportado. Usa JSON (phoenix.predictive.training/v1) o CSV.');
    }

    /**
     * @return list<string>
     */
    public function expectedCsvHeaders(PredictiveAlgorithmKind $kind): array
    {
        return match ($kind) {
            PredictiveAlgorithmKind::Maintenance => [
                'asset_tag', 'as_of', 'horizon_days', 'label_failed',
            ],
            PredictiveAlgorithmKind::Manufacturing => [
                'client_code', 'service_type', 'occurred_at', 'quantity',
            ],
            PredictiveAlgorithmKind::Inventory => [
                'client_code', 'catalog_item_code', 'requested_at', 'quantity',
            ],
        };
    }

    /**
     * @return array{kind: string, records: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    private function parseJson(string $contents, ?string $forcedKind): array
    {
        $decoded = json_decode($contents, true);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('JSON inválido.');
        }

        $contract = (string) ($decoded['contract'] ?? '');
        if ($contract !== '' && $contract !== self::CONTRACT) {
            throw new InvalidArgumentException('Contrato esperado: '.self::CONTRACT);
        }

        $kindRaw = $forcedKind ?: (string) ($decoded['kind'] ?? '');
        $kind = PredictiveAlgorithmKind::tryFromFlexible($kindRaw);
        if ($kind === null) {
            throw new InvalidArgumentException('kind inválido. Usa: '.implode(', ', PredictiveAlgorithmKind::values()));
        }

        $records = $decoded['records'] ?? null;
        if (! is_array($records)) {
            throw new InvalidArgumentException('Falta records[] en el documento.');
        }

        $normalized = [];
        foreach ($records as $i => $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException("records[{$i}] debe ser un objeto.");
            }
            $normalized[] = $this->normalizeRecord($kind, $row, $i);
        }

        return [
            'kind' => $kind->value,
            'records' => $normalized,
            'meta' => [
                'contract' => self::CONTRACT,
                'source' => 'json',
                'record_count' => count($normalized),
            ],
        ];
    }

    /**
     * @return array{kind: string, records: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    private function parseCsv(string $contents, ?string $forcedKind): array
    {
        $kind = PredictiveAlgorithmKind::tryFromFlexible($forcedKind);
        if ($kind === null) {
            throw new InvalidArgumentException('Para CSV indica kind (maintenance_hazard_v2, manufacturing_demand_v1 o inventory_demand_v1).');
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($contents)) ?: [];
        if (count($lines) < 2) {
            throw new InvalidArgumentException('CSV vacío o sin filas de datos.');
        }

        $headers = str_getcsv(array_shift($lines));
        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $headers);
        $expected = $this->expectedCsvHeaders($kind);
        foreach ($expected as $col) {
            if (! in_array($col, $headers, true)) {
                throw new InvalidArgumentException('CSV incompleto. Cabeceras requeridas: '.implode(', ', $expected));
            }
        }

        $normalized = [];
        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line);
            $row = [];
            foreach ($headers as $idx => $header) {
                $row[$header] = $cols[$idx] ?? null;
            }
            $normalized[] = $this->normalizeRecord($kind, $row, $i + 1);
        }

        return [
            'kind' => $kind->value,
            'records' => $normalized,
            'meta' => [
                'contract' => self::CONTRACT,
                'source' => 'csv',
                'record_count' => count($normalized),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRecord(PredictiveAlgorithmKind $kind, array $row, int $index): array
    {
        return match ($kind) {
            PredictiveAlgorithmKind::Maintenance => $this->normalizeMaintenance($row, $index),
            PredictiveAlgorithmKind::Manufacturing => $this->normalizeManufacturing($row, $index),
            PredictiveAlgorithmKind::Inventory => $this->normalizeInventory($row, $index),
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeMaintenance(array $row, int $index): array
    {
        $tag = trim((string) ($row['asset_tag'] ?? $row['tag'] ?? ''));
        if ($tag === '') {
            throw new InvalidArgumentException("Fila {$index}: asset_tag requerido.");
        }
        $asOf = (string) ($row['as_of'] ?? '');
        if ($asOf === '') {
            throw new InvalidArgumentException("Fila {$index}: as_of requerido (YYYY-MM-DD).");
        }
        $label = $row['label_failed'] ?? $row['label'] ?? false;
        $labelBool = filter_var($label, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($labelBool === null) {
            $labelBool = in_array((string) $label, ['1', 'true', 'yes', 'si', 'sí'], true);
        }

        return [
            'asset_tag' => $tag,
            'as_of' => $asOf,
            'horizon_days' => max(1, (int) ($row['horizon_days'] ?? 14)),
            'label_failed' => $labelBool,
            'features' => is_array($row['features'] ?? null) ? $row['features'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeManufacturing(array $row, int $index): array
    {
        $client = trim((string) ($row['client_code'] ?? ''));
        $service = trim((string) ($row['service_type'] ?? $row['routine_type'] ?? ''));
        $at = (string) ($row['occurred_at'] ?? $row['date'] ?? '');
        if ($client === '' || $service === '' || $at === '') {
            throw new InvalidArgumentException("Fila {$index}: client_code, service_type y occurred_at son requeridos.");
        }

        return [
            'client_code' => $client,
            'service_type' => $service,
            'occurred_at' => $at,
            'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeInventory(array $row, int $index): array
    {
        $client = trim((string) ($row['client_code'] ?? ''));
        $sku = trim((string) ($row['catalog_item_code'] ?? $row['sku'] ?? ''));
        $at = (string) ($row['requested_at'] ?? $row['occurred_at'] ?? $row['date'] ?? '');
        if ($client === '' || $sku === '' || $at === '') {
            throw new InvalidArgumentException("Fila {$index}: client_code, catalog_item_code y requested_at son requeridos.");
        }

        return [
            'client_code' => $client,
            'catalog_item_code' => $sku,
            'requested_at' => $at,
            'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
        ];
    }
}
