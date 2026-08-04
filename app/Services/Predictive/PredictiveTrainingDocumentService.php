<?php

namespace App\Services\Predictive;

use App\Enums\PredictiveAlgorithmKind;
use App\Models\PredictiveTrainingDocument;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PredictiveTrainingDocumentService
{
    public const TEMPLATE_DIR = 'predictive/training-templates';

    public function __construct(
        private readonly PredictiveTrainingDocumentParser $parser,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $kind = null): array
    {
        $query = PredictiveTrainingDocument::query()->orderByDesc('id');
        if ($kind) {
            $resolved = PredictiveAlgorithmKind::tryFromFlexible($kind);
            if ($resolved) {
                $query->where('kind', $resolved->value);
            }
        }

        return $query->get()->map(fn (PredictiveTrainingDocument $doc) => $this->serialize($doc))->all();
    }

    /**
     * Guía operativa del módulo (para UI de plataforma).
     *
     * @return array<string, mixed>
     */
    public function guide(): array
    {
        return [
            'title' => 'Cómo entrenar y validar',
            'summary' => 'El entrenamiento aprende calibración. La regresión mide precisión con un backtest; no subes un archivo de regresión.',
            'steps' => [
                'Elige la familia de algoritmo (mantenimiento, manufactura o inventario).',
                'Revisa la nota de información disponible y el volumen recomendado (empresas con opt-in de servicios).',
                'Opcional: descarga la plantilla, rellénala con datos reales y súbela.',
                'Entrena: Phoenix usa historial de servicios de empresas con opt-in + documentos ready seleccionados.',
                'Revisa AUC y filas de la regresión automática. Si convence, publica la versión draft.',
                'El botón «Regresión» vuelve a medir precisión sobre una versión ya creada (sin subir archivo).',
            ],
            'documents' => [
                'optional' => true,
                'formats' => ['json', 'csv'],
                'contract' => PredictiveTrainingDocumentParser::CONTRACT,
                'note' => 'Los documentos enriquecen la calibración. Sin ellos igual puedes entrenar si hay empresas con opt-in de servicios.',
            ],
            'regression' => [
                'what' => 'Backtest retrospectivo: predice en fechas del pasado y compara con lo que sí ocurrió.',
                'metric' => 'roc_auc cercano a 1 discrimina mejor; 0.5 ≈ azar. «filas» = ventanas evaluadas.',
                'requires' => 'Empresas activas con opt-in de recolección de servicios y suficiente historial validado.',
                'not_a_document' => true,
            ],
            'kinds' => collect(PredictiveAlgorithmKind::cases())->map(fn (PredictiveAlgorithmKind $kind) => [
                'kind' => $kind->value,
                'label' => $kind->label(),
                'description' => $kind->description(),
                'fields' => $this->fieldGuide($kind),
                'templates' => [
                    'json' => $kind->value.'.json',
                    'csv' => $kind->value.'.csv',
                ],
            ])->values()->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function schemas(): array
    {
        $out = [];
        foreach (PredictiveAlgorithmKind::cases() as $kind) {
            $out[] = [
                'kind' => $kind->value,
                'label' => $kind->label(),
                'description' => $kind->description(),
                'contract' => PredictiveTrainingDocumentParser::CONTRACT,
                'csv_headers' => $this->parser->expectedCsvHeaders($kind),
                'fields' => $this->fieldGuide($kind),
                'json_example' => $this->jsonExample($kind),
                'template_files' => [
                    'json' => $kind->value.'.json',
                    'csv' => $kind->value.'.csv',
                ],
            ];
        }

        return $out;
    }

    /**
     * Contenido de plantilla (desde resources/predictive/training-templates).
     *
     * @return array{filename: string, mime: string, contents: string}
     */
    public function template(string $kind, string $format = 'json'): array
    {
        $resolved = PredictiveAlgorithmKind::tryFromFlexible($kind);
        if ($resolved === null) {
            throw new InvalidArgumentException('kind inválido.');
        }

        $format = strtolower($format);
        if (! in_array($format, ['json', 'csv'], true)) {
            throw new InvalidArgumentException('format debe ser json o csv.');
        }

        $filename = $resolved->value.'.'.$format;
        $path = resource_path(self::TEMPLATE_DIR.'/'.$filename);
        if (! is_file($path)) {
            throw new InvalidArgumentException("Plantilla no encontrada: {$filename}");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new InvalidArgumentException("No se pudo leer la plantilla: {$filename}");
        }

        return [
            'filename' => $filename,
            'mime' => $format === 'json' ? 'application/json' : 'text/csv',
            'contents' => $contents,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function upload(User $actor, UploadedFile $file, string $kind, ?string $name = null): array
    {
        $resolved = PredictiveAlgorithmKind::tryFromFlexible($kind);
        if ($resolved === null) {
            throw new InvalidArgumentException('kind inválido.');
        }

        $contents = $file->get();
        if ($contents === false || $contents === '') {
            throw new InvalidArgumentException('Archivo vacío.');
        }

        try {
            $parsed = $this->parser->parse($contents, $file->getClientOriginalName(), $resolved->value);
        } catch (InvalidArgumentException $e) {
            $doc = PredictiveTrainingDocument::query()->create([
                'kind' => $resolved->value,
                'name' => $name ?: $file->getClientOriginalName(),
                'original_filename' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'disk' => 'local',
                'path' => 'predictive/training/_invalid/'.Str::uuid().'.bin',
                'byte_size' => $file->getSize() ?: 0,
                'record_count' => 0,
                'status' => PredictiveTrainingDocument::STATUS_INVALID,
                'validation_errors' => [$e->getMessage()],
                'meta' => null,
                'uploaded_by' => $actor->id,
            ]);

            return $this->serialize($doc);
        }

        $path = 'predictive/training/'.$resolved->value.'/'.Str::uuid().'_'.$file->getClientOriginalName();
        Storage::disk('local')->put($path, $contents);

        $doc = PredictiveTrainingDocument::query()->create([
            'kind' => $resolved->value,
            'name' => $name ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'original_filename' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'disk' => 'local',
            'path' => $path,
            'byte_size' => strlen($contents),
            'record_count' => count($parsed['records']),
            'status' => PredictiveTrainingDocument::STATUS_READY,
            'validation_errors' => null,
            'meta' => $parsed['meta'],
            'uploaded_by' => $actor->id,
        ]);

        $this->audit->record(
            null,
            $actor->id,
            'predictive.training_document_uploaded',
            PredictiveTrainingDocument::class,
            $doc->id,
            ['kind' => $doc->kind, 'records' => $doc->record_count],
        );

        return $this->serialize($doc);
    }

    public function destroy(PredictiveTrainingDocument $document, User $actor): void
    {
        if (Storage::disk($document->disk)->exists($document->path)) {
            Storage::disk($document->disk)->delete($document->path);
        }
        $id = $document->id;
        $document->delete();

        $this->audit->record(
            null,
            $actor->id,
            'predictive.training_document_deleted',
            PredictiveTrainingDocument::class,
            $id,
            [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PredictiveTrainingDocument $doc): array
    {
        return [
            'id' => $doc->id,
            'kind' => $doc->kind,
            'kind_label' => PredictiveAlgorithmKind::tryFromFlexible($doc->kind)?->label() ?? $doc->kind,
            'name' => $doc->name,
            'original_filename' => $doc->original_filename,
            'mime' => $doc->mime,
            'byte_size' => $doc->byte_size,
            'record_count' => $doc->record_count,
            'status' => $doc->status,
            'validation_errors' => $doc->validation_errors,
            'meta' => $doc->meta,
            'uploaded_by' => $doc->uploaded_by,
            'created_at' => $doc->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{name: string, required: bool, description: string}>
     */
    private function fieldGuide(PredictiveAlgorithmKind $kind): array
    {
        return match ($kind) {
            PredictiveAlgorithmKind::Maintenance => [
                ['name' => 'asset_tag', 'required' => true, 'description' => 'Tag del activo en Phoenix.'],
                ['name' => 'as_of', 'required' => true, 'description' => 'Fecha de corte de la predicción (YYYY-MM-DD).'],
                ['name' => 'horizon_days', 'required' => true, 'description' => 'Ventana en días (p. ej. 7, 14, 30).'],
                ['name' => 'label_failed', 'required' => true, 'description' => 'true si hubo falla/servicio requerido en esa ventana; false si no.'],
            ],
            PredictiveAlgorithmKind::Manufacturing => [
                ['name' => 'client_code', 'required' => true, 'description' => 'Código del cliente en Phoenix.'],
                ['name' => 'service_type', 'required' => true, 'description' => 'Nombre del tipo de servicio de manufactura.'],
                ['name' => 'occurred_at', 'required' => true, 'description' => 'Fecha en que ocurrió el servicio (YYYY-MM-DD).'],
                ['name' => 'quantity', 'required' => false, 'description' => 'Cantidad relativa (default 1).'],
            ],
            PredictiveAlgorithmKind::Inventory => [
                ['name' => 'client_code', 'required' => true, 'description' => 'Código del cliente final.'],
                ['name' => 'catalog_item_code', 'required' => true, 'description' => 'Código del artículo en el catálogo.'],
                ['name' => 'requested_at', 'required' => true, 'description' => 'Fecha de la solicitud/compra (YYYY-MM-DD).'],
                ['name' => 'quantity', 'required' => false, 'description' => 'Cantidad solicitada (default 1).'],
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonExample(PredictiveAlgorithmKind $kind): array
    {
        $template = $this->safeTemplatePayload($kind);

        return $template ?? match ($kind) {
            PredictiveAlgorithmKind::Maintenance => [
                'contract' => PredictiveTrainingDocumentParser::CONTRACT,
                'kind' => $kind->value,
                'records' => [
                    [
                        'asset_tag' => 'SBX-L200-01',
                        'as_of' => '2026-01-15',
                        'horizon_days' => 14,
                        'label_failed' => true,
                    ],
                ],
            ],
            PredictiveAlgorithmKind::Manufacturing => [
                'contract' => PredictiveTrainingDocumentParser::CONTRACT,
                'kind' => $kind->value,
                'records' => [
                    [
                        'client_code' => 'SANDBOX-CLI-001',
                        'service_type' => 'Orden de manufactura',
                        'occurred_at' => '2026-02-01',
                        'quantity' => 1,
                    ],
                ],
            ],
            PredictiveAlgorithmKind::Inventory => [
                'contract' => PredictiveTrainingDocumentParser::CONTRACT,
                'kind' => $kind->value,
                'records' => [
                    [
                        'client_code' => 'SANDBOX-CLI-001',
                        'catalog_item_code' => 'SBX-L200-2018',
                        'requested_at' => '2026-02-10',
                        'quantity' => 2,
                    ],
                ],
            ],
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function safeTemplatePayload(PredictiveAlgorithmKind $kind): ?array
    {
        $path = resource_path(self::TEMPLATE_DIR.'/'.$kind->value.'.json');
        if (! is_file($path)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }
}
