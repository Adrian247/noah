<?php

namespace App\Services\Predictive;

use App\Enums\RoutineStatus;
use App\Models\Company;
use App\Models\PredictiveAlgorithmVersion;
use App\Models\Routine;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Ciclo de vida del algoritmo predictivo en plataforma: entrenar (draft), publicar, archivar.
 * El entrenamiento solo usa rutinas validadas de empresas con opt-in de recolección.
 */
class PredictiveAlgorithmVersionService
{
    public function __construct(
        private readonly AuditLogger $audit,
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
     * @return list<array<string, mixed>>
     */
    public function publishedForCompanies(): array
    {
        return PredictiveAlgorithmVersion::query()
            ->where('status', PredictiveAlgorithmVersion::STATUS_PUBLISHED)
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (PredictiveAlgorithmVersion $v) => [
                'id' => $v->id,
                'semver' => $v->semver,
                'kind' => $v->kind,
                'notes' => $v->notes,
                'published_at' => $v->published_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Entrena una nueva versión draft a partir del historial de rutinas de clientes con opt-in.
     *
     * @param  array{bump?: string, notes?: string|null}  $options
     * @return array<string, mixed>
     */
    public function train(User $actor, array $options = []): array
    {
        $bump = $options['bump'] ?? 'minor';
        if (! in_array($bump, ['major', 'minor', 'patch'], true)) {
            throw new InvalidArgumentException('bump debe ser major, minor o patch.');
        }

        $optedIn = Company::query()
            ->where('allow_predictive_training_collection', true)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $summary = $this->collectTrainingSummary($optedIn);

        $semver = $this->nextSemver($bump);
        $version = PredictiveAlgorithmVersion::query()->create([
            'semver' => $semver,
            'status' => PredictiveAlgorithmVersion::STATUS_DRAFT,
            'kind' => 'hazard_routines_v1',
            'notes' => $options['notes'] ?? null,
            'metrics' => [
                'baseline_kind' => 'hazard_routines_v1',
                'trained_at' => now()->toIso8601String(),
            ],
            'training_summary' => $summary,
            'created_by' => $actor->id,
        ]);

        $this->audit->record(
            null,
            $actor->id,
            'predictive.algorithm_trained',
            PredictiveAlgorithmVersion::class,
            $version->id,
            [
                'semver' => $version->semver,
                'companies_opted_in' => count($optedIn),
                'validated_routines' => $summary['validated_routines'] ?? 0,
            ],
        );

        return $this->serialize($version);
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
            ['semver' => $version->semver],
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
     * @param  list<int>  $companyIds
     * @return array<string, mixed>
     */
    private function collectTrainingSummary(array $companyIds): array
    {
        if ($companyIds === []) {
            return [
                'companies_opted_in' => 0,
                'validated_routines' => 0,
                'assets_covered' => 0,
                'note' => 'Ninguna empresa ha habilitado la recolección para entrenamiento.',
            ];
        }

        $validatedStatuses = [
            RoutineStatus::Validated->value,
            RoutineStatus::PendingBilling->value,
            RoutineStatus::Invoiced->value,
        ];

        $since = CarbonImmutable::today()->subYears(2)->toDateString();

        $routines = Routine::withoutGlobalScope('company')
            ->whereIn('company_id', $companyIds)
            ->whereIn('status', $validatedStatuses)
            ->where(function ($q) use ($since) {
                $q->whereDate('scheduled_at', '>=', $since)
                    ->orWhereHas('latestExecution', fn ($e) => $e->whereNotNull('validated_at')->whereDate('validated_at', '>=', $since));
            })
            ->count();

        $assets = Routine::withoutGlobalScope('company')
            ->whereIn('company_id', $companyIds)
            ->whereIn('status', $validatedStatuses)
            ->distinct('asset_id')
            ->count('asset_id');

        $byCompany = Routine::withoutGlobalScope('company')
            ->whereIn('company_id', $companyIds)
            ->whereIn('status', $validatedStatuses)
            ->groupBy('company_id')
            ->selectRaw('company_id, COUNT(*) as routines')
            ->pluck('routines', 'company_id')
            ->all();

        return [
            'companies_opted_in' => count($companyIds),
            'validated_routines' => $routines,
            'assets_covered' => $assets,
            'routines_by_company' => $byCompany,
            'window_from' => $since,
            'collected_at' => now()->toIso8601String(),
            'note' => 'Corpus interno: solo rutinas validadas de empresas con opt-in. No se expone fuera de Phoenix.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PredictiveAlgorithmVersion $version): array
    {
        return [
            'id' => $version->id,
            'semver' => $version->semver,
            'status' => $version->status,
            'kind' => $version->kind,
            'notes' => $version->notes,
            'metrics' => $version->metrics,
            'training_summary' => $version->training_summary,
            'artifact_path' => $version->artifact_path,
            'created_by' => $version->created_by,
            'published_by' => $version->published_by,
            'published_at' => $version->published_at?->toIso8601String(),
            'created_at' => $version->created_at?->toIso8601String(),
        ];
    }
}
