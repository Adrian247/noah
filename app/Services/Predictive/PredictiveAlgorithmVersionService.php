<?php

namespace App\Services\Predictive;

use App\Enums\PredictiveAlgorithmKind;
use App\Enums\RoutineStatus;
use App\Enums\ServiceCategory;
use App\Models\Company;
use App\Models\PredictiveAlgorithmVersion;
use App\Models\PredictiveTrainingDocument;
use App\Models\Routine;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Ciclo de vida del algoritmo predictivo en plataforma (root): entrenar, publicar, archivar.
 * Entrena por familia (mantenimiento / manufactura / inventario) con corpus opt-in + documentos.
 */
class PredictiveAlgorithmVersionService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PredictiveMaintenanceService $maintenance,
        private readonly ServiceDemandEngine $demandEngine,
        private readonly PredictiveTrainingDocumentParser $documentParser,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        return PredictiveAlgorithmVersion::query()
            ->orderByDesc('id')
            ->get()
            ->map(fn (PredictiveAlgorithmVersion $v) => $this->serialize($v))
            ->all();
    }

    /**
     * Snapshot persistente del corpus disponible para entrenamiento (empresas con opt-in).
     *
     * @return array<string, mixed>
     */
    public function corpusAvailability(?string $kind = null): array
    {
        $optedIn = Company::query()
            ->where('allow_predictive_training_collection', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $companyIds = $optedIn->pluck('id')->map(fn ($id) => (int) $id)->all();
        $kinds = $kind
            ? array_values(array_filter([PredictiveAlgorithmKind::tryFromFlexible($kind)]))
            : PredictiveAlgorithmKind::cases();

        $byKind = [];
        foreach ($kinds as $resolved) {
            $byKind[] = $this->corpusForKind($resolved, $companyIds);
        }

        $readyDocs = PredictiveTrainingDocument::query()
            ->where('status', PredictiveTrainingDocument::STATUS_READY)
            ->count();

        $overallLevel = $this->worstVolumeLevel(array_column($byKind, 'volume_level'));
        $note = $this->persistentCorpusNote($optedIn->count(), $readyDocs, $overallLevel);

        return [
            'note' => $note,
            'opt_in' => [
                'companies_count' => $optedIn->count(),
                'companies' => $optedIn->map(fn (Company $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                ])->values()->all(),
                'reminder' => 'Solo entran datos de empresas cuyo administrador activó «Permitir a Phoenix recopilar información de servicios para entrenamiento» en Configuración → Predictivo.',
            ],
            'ready_documents' => $readyDocs,
            'overall_volume_level' => $overallLevel,
            'overall_volume_label' => $this->volumeLabel($overallLevel),
            'overall_volume_hint' => $this->volumeHint($overallLevel),
            'kinds' => $byKind,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  list<int>  $companyIds
     * @return array<string, mixed>
     */
    private function corpusForKind(PredictiveAlgorithmKind $kind, array $companyIds): array
    {
        $docsReady = PredictiveTrainingDocument::query()
            ->where('kind', $kind->value)
            ->where('status', PredictiveTrainingDocument::STATUS_READY)
            ->get(['id', 'record_count']);

        $documentRecords = (int) $docsReady->sum('record_count');
        $validatedStatuses = [
            RoutineStatus::Validated->value,
            RoutineStatus::PendingBilling->value,
            RoutineStatus::Invoiced->value,
        ];
        $since = CarbonImmutable::today()->subYears(2)->toDateString();

        $services = 0;
        $assets = 0;
        $clients = 0;
        $inventoryEvents = 0;

        if ($companyIds !== []) {
            if ($kind === PredictiveAlgorithmKind::Inventory) {
                $inventoryEvents = (int) \Illuminate\Support\Facades\DB::table('routine_consumptions as c')
                    ->join('routine_executions as e', 'e.id', '=', 'c.routine_execution_id')
                    ->join('routines as r', 'r.id', '=', 'e.routine_id')
                    ->whereIn('r.company_id', $companyIds)
                    ->whereIn('r.status', $validatedStatuses)
                    ->whereNotNull('r.client_id')
                    ->whereNotNull('e.validated_at')
                    ->whereDate('e.validated_at', '>=', $since)
                    ->count();
                $clients = (int) \Illuminate\Support\Facades\DB::table('routine_consumptions as c')
                    ->join('routine_executions as e', 'e.id', '=', 'c.routine_execution_id')
                    ->join('routines as r', 'r.id', '=', 'e.routine_id')
                    ->whereIn('r.company_id', $companyIds)
                    ->whereIn('r.status', $validatedStatuses)
                    ->whereNotNull('r.client_id')
                    ->whereNotNull('e.validated_at')
                    ->whereDate('e.validated_at', '>=', $since)
                    ->selectRaw('COUNT(DISTINCT r.client_id) as aggregate')
                    ->value('aggregate');
                $services = $inventoryEvents;
            } else {
                $query = Routine::withoutGlobalScope('company')
                    ->whereIn('company_id', $companyIds)
                    ->whereIn('status', $validatedStatuses)
                    ->where(function ($q) use ($since) {
                        $q->whereDate('scheduled_at', '>=', $since)
                            ->orWhereHas('latestExecution', fn ($e) => $e->whereNotNull('validated_at')->whereDate('validated_at', '>=', $since));
                    });

                if ($kind === PredictiveAlgorithmKind::Maintenance) {
                    $query->whereHas('routineType', fn ($q) => $q->where('service_category', ServiceCategory::Maintenance->value));
                } else {
                    $query->whereHas('routineType', fn ($q) => $q->where('service_category', ServiceCategory::Manufacturing->value));
                }

                $services = (clone $query)->count();
                if ($kind === PredictiveAlgorithmKind::Maintenance) {
                    $assets = (clone $query)->whereNotNull('asset_id')->distinct('asset_id')->count('asset_id');
                } else {
                    $clients = (clone $query)->whereNotNull('client_id')->distinct('client_id')->count('client_id');
                }
            }
        }

        $effectiveEvents = $services + $documentRecords;
        $level = $this->assessVolumeLevel($kind, $effectiveEvents, $assets, $clients, count($companyIds));

        return [
            'kind' => $kind->value,
            'kind_label' => $kind->label(),
            'companies_opted_in' => count($companyIds),
            'validated_services' => $services,
            'assets_covered' => $kind === PredictiveAlgorithmKind::Maintenance ? $assets : null,
            'clients_covered' => $kind !== PredictiveAlgorithmKind::Maintenance ? $clients : null,
            'documents_ready' => $docsReady->count(),
            'document_records' => $documentRecords,
            'effective_events' => $effectiveEvents,
            'volume_level' => $level,
            'volume_label' => $this->volumeLabel($level),
            'volume_hint' => $this->volumeHint($level),
            'window_from' => $since,
        ];
    }

    /**
     * @param  list<string>  $levels
     */
    private function worstVolumeLevel(array $levels): string
    {
        $rank = ['insufficient' => 0, 'limited' => 1, 'adequate' => 2, 'strong' => 3];
        if ($levels === []) {
            return 'insufficient';
        }

        $worst = 'strong';
        foreach ($levels as $level) {
            if (($rank[$level] ?? 0) < ($rank[$worst] ?? 3)) {
                $worst = $level;
            }
        }

        return $worst;
    }

    private function assessVolumeLevel(
        PredictiveAlgorithmKind $kind,
        int $events,
        int $assets,
        int $clients,
        int $companies,
    ): string {
        if ($companies === 0 && $events === 0) {
            return 'insufficient';
        }

        if ($kind === PredictiveAlgorithmKind::Maintenance) {
            if ($events < 50 || $assets < 3) {
                return 'insufficient';
            }
            if ($events < 200 || $assets < 10) {
                return 'limited';
            }
            if ($events < 1000 || $assets < 30) {
                return 'adequate';
            }

            return 'strong';
        }

        $entities = max($clients, 0);
        if ($events < 40 || $entities < 2) {
            return 'insufficient';
        }
        if ($events < 150 || $entities < 8) {
            return 'limited';
        }
        if ($events < 600 || $entities < 20) {
            return 'adequate';
        }

        return 'strong';
    }

    private function volumeLabel(string $level): string
    {
        return match ($level) {
            'strong' => 'Volumen alto — apto para entrenar',
            'adequate' => 'Volumen suficiente — entrenamiento útil',
            'limited' => 'Volumen limitado — resultados preliminares',
            default => 'Volumen insuficiente — conviene más historial o documentos',
        };
    }

    private function volumeHint(string $level): string
    {
        return match ($level) {
            'strong' => 'Hay masa crítica de servicios validados (y/o documentos). La regresión debería ser representativa.',
            'adequate' => 'Hay señal útil para calibrar. Se recomienda publicar solo si el AUC de regresión es estable.',
            'limited' => 'Puedes entrenar, pero el modelo generalizará poco. Suma más empresas con opt-in o documentos etiquetados.',
            default => 'Aún no hay datos significativos. Pide a administradores de cliente habilitar el opt-in o carga documentos de entrenamiento.',
        };
    }

    private function persistentCorpusNote(int $companies, int $readyDocs, string $level): string
    {
        if ($companies === 0 && $readyDocs === 0) {
            return 'No hay información disponible para entrenamiento: ninguna empresa con opt-in de servicios y sin documentos ready. El administrador de cada cliente habilita el permiso en Configuración → Predictivo.';
        }

        if ($companies === 0) {
            return "Sin empresas con opt-in. Hay {$readyDocs} documento(s) ready; el entrenamiento solo usará archivos cargados hasta que algún cliente compartá historial de servicios.";
        }

        return "Información disponible para entrenamiento: {$companies} empresa(s) con opt-in de servicios"
            .($readyDocs > 0 ? " y {$readyDocs} documento(s) ready" : '')
            .'. '.$this->volumeLabel($level).'. '
            .'Solo se usa historial de servicios de clientes que autorizaron el uso para mejorar el algoritmo (no se vende ni se expone fuera de Phoenix).';
    }

    /**
     * Versiones publicadas seleccionables por el administrador de empresa (mantenimiento).
     *
     * @return list<array<string, mixed>>
     */
    public function publishedForCompanies(): array
    {
        return PredictiveAlgorithmVersion::query()
            ->where('status', PredictiveAlgorithmVersion::STATUS_PUBLISHED)
            ->whereIn('kind', [
                PredictiveAlgorithmKind::Maintenance->value,
                PredictiveAlgorithmKind::LEGACY_MAINTENANCE,
            ])
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (PredictiveAlgorithmVersion $v) => [
                'id' => $v->id,
                'semver' => $v->semver,
                'kind' => $v->kind,
                'kind_label' => PredictiveAlgorithmKind::tryFromFlexible($v->kind)?->label() ?? $v->kind,
                'notes' => $v->notes,
                'published_at' => $v->published_at?->toIso8601String(),
            ])
            ->all();
    }

    public function publishedVersion(PredictiveAlgorithmKind $kind): ?PredictiveAlgorithmVersion
    {
        $kinds = $kind === PredictiveAlgorithmKind::Maintenance
            ? [PredictiveAlgorithmKind::Maintenance->value, PredictiveAlgorithmKind::LEGACY_MAINTENANCE]
            : [$kind->value];

        return PredictiveAlgorithmVersion::query()
            ->where('status', PredictiveAlgorithmVersion::STATUS_PUBLISHED)
            ->whereIn('kind', $kinds)
            ->orderByDesc('published_at')
            ->first();
    }

    /**
     * Metadata de versión publicada para respuestas de predicción (todas las familias).
     *
     * @return array<string, mixed>
     */
    public function publishedModelDescriptor(PredictiveAlgorithmKind $kind, string $selection = 'published_latest'): array
    {
        $version = $this->publishedVersion($kind);

        return [
            'kind' => $kind->value,
            'kind_label' => $kind->label(),
            'version' => $version?->semver ?? $kind->value,
            'algorithm_version_id' => $version?->id,
            'algorithm_semver' => $version?->semver,
            'algorithm_kind' => $kind->value,
            'selection' => $selection,
            'feature_source' => match ($kind) {
                PredictiveAlgorithmKind::Maintenance => 'routines',
                PredictiveAlgorithmKind::Manufacturing => 'manufacturing_services',
                PredictiveAlgorithmKind::Inventory => 'inventory_consumptions',
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function attachPublishedModel(array $payload, PredictiveAlgorithmKind $kind, string $selection = 'published_latest'): array
    {
        $payload['model'] = $this->publishedModelDescriptor($kind, $selection);

        return $payload;
    }

    /**
     * Estado de las 3 familias para la UI de configuración de empresa.
     *
     * @return list<array<string, mixed>>
     */
    public function companyAlgorithmsStatus(?Company $company): array
    {
        $pinnedId = $company?->predictive_algorithm_version_id;
        $pinned = $pinnedId
            ? PredictiveAlgorithmVersion::query()->find($pinnedId)
            : null;

        $status = [];
        foreach (PredictiveAlgorithmKind::cases() as $kind) {
            $latest = $this->publishedVersion($kind);
            $selectable = $kind === PredictiveAlgorithmKind::Maintenance;
            $active = $latest;
            $selectionMode = $latest === null ? 'unavailable' : 'auto';

            if ($selectable && $pinned !== null) {
                $pinnedKind = PredictiveAlgorithmKind::tryFromFlexible($pinned->kind);
                if ($pinnedKind === PredictiveAlgorithmKind::Maintenance && $pinned->isPublished()) {
                    $active = $pinned;
                    $selectionMode = 'pinned';
                }
            }

            $status[] = [
                'kind' => $kind->value,
                'kind_label' => $kind->label(),
                'description' => $kind->description(),
                'selectable' => $selectable,
                'selection_mode' => $selectionMode,
                'selection_hint' => match (true) {
                    ! $selectable => 'Usa automáticamente la versión publicada más reciente de plataforma.',
                    $selectionMode === 'pinned' => 'Versión fijada por la empresa.',
                    $selectionMode === 'auto' => 'Sin fijar: usa la publicada más reciente.',
                    default => 'Aún no hay versión publicada de esta familia.',
                },
                'selected_version_id' => $selectable ? $pinnedId : null,
                'active_version' => $active ? [
                    'id' => $active->id,
                    'semver' => $active->semver,
                    'kind' => $active->kind,
                    'kind_label' => PredictiveAlgorithmKind::tryFromFlexible($active->kind)?->label() ?? $active->kind,
                    'published_at' => $active->published_at?->toIso8601String(),
                ] : null,
                'available_versions' => $selectable ? $this->publishedForCompanies() : [],
            ];
        }

        return $status;
    }

    /**
     * Calibración de la versión publicada más reciente de una familia de algoritmo.
     *
     * @return array<string, mixed>
     */
    public function publishedCalibration(PredictiveAlgorithmKind $kind): array
    {
        $version = $this->publishedVersion($kind);

        return is_array($version?->calibration) ? $version->calibration : [];
    }

    /**
     * @param  array{
     *     bump?: string,
     *     notes?: string|null,
     *     kind?: string,
     *     document_ids?: list<int>,
     *     run_regression?: bool
     * }  $options
     * @return array<string, mixed>
     */
    public function train(User $actor, array $options = []): array
    {
        $bump = $options['bump'] ?? 'minor';
        if (! in_array($bump, ['major', 'minor', 'patch'], true)) {
            throw new InvalidArgumentException('bump debe ser major, minor o patch.');
        }

        $kind = PredictiveAlgorithmKind::tryFromFlexible($options['kind'] ?? PredictiveAlgorithmKind::Maintenance->value)
            ?? PredictiveAlgorithmKind::Maintenance;

        $optedIn = Company::query()
            ->where('allow_predictive_training_collection', true)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $documentIds = array_values(array_map('intval', $options['document_ids'] ?? []));
        $documents = $this->loadDocuments($kind, $documentIds);
        $documentRecords = $this->loadDocumentRecords($kind, $documents);

        $summary = $this->collectTrainingSummary($kind, $optedIn, $documents, $documentRecords);
        $calibration = $this->fitCalibration($kind, $optedIn, $documentRecords);
        $regression = ($options['run_regression'] ?? true)
            ? $this->runRegression($kind, $optedIn, $calibration)
            : null;

        $semver = $this->nextSemver($bump);
        $version = PredictiveAlgorithmVersion::query()->create([
            'semver' => $semver,
            'status' => PredictiveAlgorithmVersion::STATUS_DRAFT,
            'kind' => $kind->value,
            'notes' => $options['notes'] ?? null,
            'metrics' => [
                'baseline_kind' => $kind->value,
                'trained_at' => now()->toIso8601String(),
                'roc_auc' => $regression['roc_auc'] ?? null,
                'rows' => $regression['rows'] ?? null,
            ],
            'calibration' => $calibration,
            'regression_report' => $regression,
            'training_summary' => $summary,
            'created_by' => $actor->id,
        ]);

        foreach ($documents as $doc) {
            $doc->update(['status' => PredictiveTrainingDocument::STATUS_CONSUMED]);
        }

        $this->audit->record(
            null,
            $actor->id,
            'predictive.algorithm_trained',
            PredictiveAlgorithmVersion::class,
            $version->id,
            [
                'semver' => $version->semver,
                'kind' => $kind->value,
                'companies_opted_in' => count($optedIn),
                'documents' => count($documents),
                'roc_auc' => $regression['roc_auc'] ?? null,
            ],
        );

        return $this->serialize($version);
    }

    /**
     * Ejecuta regresión de precisión sobre una versión draft/publicada (root).
     *
     * @return array<string, mixed>
     */
    public function runRegressionForVersion(PredictiveAlgorithmVersion $version): array
    {
        $kind = PredictiveAlgorithmKind::tryFromFlexible($version->kind) ?? PredictiveAlgorithmKind::Maintenance;
        $optedIn = Company::query()
            ->where('allow_predictive_training_collection', true)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $report = $this->runRegression($kind, $optedIn, is_array($version->calibration) ? $version->calibration : []);
        $version->update([
            'regression_report' => $report,
            'metrics' => array_merge($version->metrics ?? [], [
                'roc_auc' => $report['roc_auc'] ?? null,
                'rows' => $report['rows'] ?? null,
                'regression_at' => now()->toIso8601String(),
            ]),
        ]);

        return $report;
    }

    /**
     * Actualiza la nota descriptiva de una versión (draft, publicada o archivada).
     *
     * @return array<string, mixed>
     */
    public function updateNotes(PredictiveAlgorithmVersion $version, User $actor, ?string $notes): array
    {
        $normalized = $notes === null ? null : trim($notes);
        if ($normalized === '') {
            $normalized = null;
        }

        $previous = $version->notes;
        $version->update(['notes' => $normalized]);

        $this->audit->record(
            null,
            $actor->id,
            'predictive.algorithm_notes_updated',
            PredictiveAlgorithmVersion::class,
            $version->id,
            [
                'semver' => $version->semver,
                'kind' => $version->kind,
                'had_notes' => $previous !== null && $previous !== '',
                'has_notes' => $normalized !== null,
            ],
        );

        return $this->serialize($version->fresh() ?? $version);
    }

    /**
     * @return array<string, mixed>
     */
    public function publish(PredictiveAlgorithmVersion $version, User $actor): array
    {
        if (! $version->isDraft()) {
            throw new InvalidArgumentException('Solo se pueden publicar versiones en borrador.');
        }

        $version->update([
            'status' => PredictiveAlgorithmVersion::STATUS_PUBLISHED,
            'published_by' => $actor->id,
            'published_at' => now(),
        ]);

        $this->audit->record(
            null,
            $actor->id,
            'predictive.algorithm_published',
            PredictiveAlgorithmVersion::class,
            $version->id,
            ['semver' => $version->semver, 'kind' => $version->kind],
        );

        return $this->serialize($version->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function archive(PredictiveAlgorithmVersion $version, User $actor): array
    {
        if ($version->isDraft()) {
            throw new InvalidArgumentException('Archiva solo versiones publicadas.');
        }

        $version->update(['status' => PredictiveAlgorithmVersion::STATUS_ARCHIVED]);

        Company::query()
            ->where('predictive_algorithm_version_id', $version->id)
            ->update(['predictive_algorithm_version_id' => null]);

        $this->audit->record(
            null,
            $actor->id,
            'predictive.algorithm_archived',
            PredictiveAlgorithmVersion::class,
            $version->id,
            ['semver' => $version->semver],
        );

        return $this->serialize($version->fresh());
    }

    private function nextSemver(string $bump): string
    {
        $latest = PredictiveAlgorithmVersion::query()
            ->orderByDesc('id')
            ->value('semver');

        if ($latest === null || ! preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $latest, $m)) {
            return match ($bump) {
                'major' => '1.0.0',
                'minor' => '0.1.0',
                default => '0.0.1',
            };
        }

        $major = (int) $m[1];
        $minor = (int) $m[2];
        $patch = (int) $m[3];

        return match ($bump) {
            'major' => ($major + 1).'.0.0',
            'minor' => $major.'.'.($minor + 1).'.0',
            default => $major.'.'.$minor.'.'.($patch + 1),
        };
    }

    /**
     * @param  list<int>  $documentIds
     * @return list<PredictiveTrainingDocument>
     */
    private function loadDocuments(PredictiveAlgorithmKind $kind, array $documentIds): array
    {
        if ($documentIds === []) {
            return [];
        }

        $docs = PredictiveTrainingDocument::query()
            ->whereIn('id', $documentIds)
            ->where('kind', $kind->value)
            ->where('status', PredictiveTrainingDocument::STATUS_READY)
            ->get();

        if ($docs->count() !== count(array_unique($documentIds))) {
            throw new InvalidArgumentException('Uno o más documentos no existen, no están ready o no coinciden con el kind.');
        }

        return $docs->all();
    }

    /**
     * @param  list<PredictiveTrainingDocument>  $documents
     * @return list<array<string, mixed>>
     */
    private function loadDocumentRecords(PredictiveAlgorithmKind $kind, array $documents): array
    {
        $records = [];
        foreach ($documents as $doc) {
            if (! Storage::disk($doc->disk)->exists($doc->path)) {
                throw new InvalidArgumentException("Documento #{$doc->id} sin archivo en disco.");
            }
            $raw = Storage::disk($doc->disk)->get($doc->path);
            $parsed = $this->documentParser->parse($raw, $doc->original_filename, $kind->value);
            foreach ($parsed['records'] as $record) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * @param  list<int>  $companyIds
     * @param  list<PredictiveTrainingDocument>  $documents
     * @param  list<array<string, mixed>>  $documentRecords
     * @return array<string, mixed>
     */
    private function collectTrainingSummary(
        PredictiveAlgorithmKind $kind,
        array $companyIds,
        array $documents,
        array $documentRecords,
    ): array {
        $base = [
            'kind' => $kind->value,
            'kind_label' => $kind->label(),
            'companies_opted_in' => count($companyIds),
            'documents_used' => count($documents),
            'document_records' => count($documentRecords),
            'collected_at' => now()->toIso8601String(),
        ];

        if ($companyIds === []) {
            return array_merge($base, [
                'validated_services' => 0,
                'note' => 'Ninguna empresa con opt-in. El entrenamiento usará solo documentos cargados (si hay).',
            ]);
        }

        $validatedStatuses = [
            RoutineStatus::Validated->value,
            RoutineStatus::PendingBilling->value,
            RoutineStatus::Invoiced->value,
        ];
        $since = CarbonImmutable::today()->subYears(2)->toDateString();

        $query = Routine::withoutGlobalScope('company')
            ->whereIn('company_id', $companyIds)
            ->whereIn('status', $validatedStatuses)
            ->where(function ($q) use ($since) {
                $q->whereDate('scheduled_at', '>=', $since)
                    ->orWhereHas('latestExecution', fn ($e) => $e->whereNotNull('validated_at')->whereDate('validated_at', '>=', $since));
            });

        if ($kind === PredictiveAlgorithmKind::Maintenance) {
            $query->whereHas('routineType', fn ($q) => $q->where('service_category', ServiceCategory::Maintenance->value));
        } elseif ($kind === PredictiveAlgorithmKind::Manufacturing) {
            $query->whereHas('routineType', fn ($q) => $q->where('service_category', ServiceCategory::Manufacturing->value));
        }

        $services = (clone $query)->count();
        $assets = $kind === PredictiveAlgorithmKind::Maintenance
            ? (clone $query)->whereNotNull('asset_id')->distinct('asset_id')->count('asset_id')
            : null;
        $clients = $kind !== PredictiveAlgorithmKind::Maintenance
            ? (clone $query)->whereNotNull('client_id')->distinct('client_id')->count('client_id')
            : null;

        return array_merge($base, [
            'validated_services' => $services,
            'validated_routines' => $services,
            'assets_covered' => $assets,
            'clients_covered' => $clients,
            'window_from' => $since,
            'note' => 'Corpus interno opt-in + documentos de entrenamiento. Solo root puede entrenar.',
        ]);
    }

    /**
     * @param  list<int>  $companyIds
     * @param  list<array<string, mixed>>  $documentRecords
     * @return array<string, mixed>
     */
    private function fitCalibration(PredictiveAlgorithmKind $kind, array $companyIds, array $documentRecords): array
    {
        if ($kind === PredictiveAlgorithmKind::Maintenance) {
            $weights = [
                'intensidad_servicios' => 1.15,
                'backlog_servicios' => 1.1,
                'incumplimiento_preventivo' => 1.2,
                'servicio_atrasado' => 1.1,
                'rutina_atrasada' => 1.1, // compat con calibraciones antiguas
            ];
            $positives = count(array_filter($documentRecords, fn ($r) => ! empty($r['label_failed'])));
            $total = max(1, count($documentRecords));
            $rate = $positives / $total;
            $global = $documentRecords === [] ? 1.0 : max(0.75, min(1.5, 0.85 + $rate));

            return [
                'global_hazard_multiplier' => round($global, 4),
                'driver_weights' => $weights,
                'labeled_rows' => count($documentRecords),
                'positive_rate' => round($rate, 4),
            ];
        }

        $pairBoosts = [];
        foreach ($documentRecords as $record) {
            if ($kind === PredictiveAlgorithmKind::Manufacturing) {
                $key = strtolower(trim(($record['client_code'] ?? '').'|'.($record['service_type'] ?? '')));
            } else {
                $key = strtolower(trim(($record['client_code'] ?? '').'|'.($record['catalog_item_code'] ?? '')));
            }
            if ($key === '|' || $key === '') {
                continue;
            }
            $qty = max(1, (int) ($record['quantity'] ?? 1));
            $pairBoosts[$key] = ($pairBoosts[$key] ?? 1.0) + log(1 + $qty);
        }

        return [
            'global_rate_multiplier' => $documentRecords === [] ? 1.0 : 1.05,
            'pair_boosts' => $pairBoosts,
            'labeled_rows' => count($documentRecords),
            'companies_opted_in' => count($companyIds),
        ];
    }

    /**
     * @param  list<int>  $companyIds
     * @param  array<string, mixed>  $calibration
     * @return array<string, mixed>
     */
    private function runRegression(PredictiveAlgorithmKind $kind, array $companyIds, array $calibration): array
    {
        if ($companyIds === []) {
            return [
                'rows' => 0,
                'notes' => ['Sin empresas con opt-in para regresión.'],
            ];
        }

        $reports = [];
        foreach (array_slice($companyIds, 0, 5) as $companyId) {
            $reports[] = match ($kind) {
                PredictiveAlgorithmKind::Maintenance => $this->maintenance->backtest($companyId, 14, 14),
                PredictiveAlgorithmKind::Manufacturing => $this->demandEngine->backtestManufacturing($companyId, 30, 14),
                PredictiveAlgorithmKind::Inventory => $this->demandEngine->backtestInventory($companyId, 30, 14),
            };
        }

        $rows = array_sum(array_map(fn ($r) => (int) ($r['rows'] ?? 0), $reports));
        $aucs = array_values(array_filter(array_map(fn ($r) => $r['roc_auc'] ?? null, $reports), fn ($v) => $v !== null));
        $avgAuc = $aucs === [] ? null : round(array_sum($aucs) / count($aucs), 4);

        return [
            'kind' => $kind->value,
            'companies_evaluated' => count($reports),
            'rows' => $rows,
            'roc_auc' => $avgAuc,
            'company_reports' => $reports,
            'calibration_applied' => $calibration !== [],
            'ran_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PredictiveAlgorithmVersion $version): array
    {
        $kind = PredictiveAlgorithmKind::tryFromFlexible($version->kind);

        return [
            'id' => $version->id,
            'semver' => $version->semver,
            'status' => $version->status,
            'kind' => $version->kind,
            'kind_label' => $kind?->label() ?? $version->kind,
            'notes' => $version->notes,
            'metrics' => $version->metrics,
            'calibration' => $version->calibration,
            'regression_report' => $version->regression_report,
            'training_summary' => $version->training_summary,
            'artifact_path' => $version->artifact_path,
            'created_by' => $version->created_by,
            'published_by' => $version->published_by,
            'published_at' => $version->published_at?->toIso8601String(),
            'created_at' => $version->created_at?->toIso8601String(),
        ];
    }
}
