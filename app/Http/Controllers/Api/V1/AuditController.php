<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Models\AuditEntry;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Routine;
use App\Models\WorkflowInstance;
use App\Support\AccessChannel;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeViewer($request);

        $companyId = app(CurrentCompany::class)->id();

        $entries = AuditEntry::query()
            ->with('actor:id,name,email')
            ->tap(fn (Builder $q) => $this->constrainVisibleToCompany($q, $companyId))
            ->when($request->query('actor_user_id'), fn ($q, $actorId) => $q->where('actor_user_id', (int) $actorId))
            ->when($request->query('action'), fn ($q, $action) => $q->where('action', 'like', '%'.$action.'%'))
            ->when($request->query('access_channel'), function ($q, $channel) {
                $q->where('metadata->access_channel', $channel);
            })
            ->when($request->query('correlation_id'), fn ($q, $id) => $q->where('correlation_id', $id))
            ->when($request->query('routine_id'), function ($q, $routineId) use ($companyId) {
                $this->constrainToRoutine($q, (int) $routineId, $companyId);
            })
            ->when($request->filled('q'), function ($q) use ($request, $companyId) {
                $this->constrainBySearch($q, trim((string) $request->query('q')), $companyId);
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate((int) $request->query('per_page', 25));

        $pageItems = collect($entries->items());
        $contextByCorrelation = $this->routineContextByCorrelation(
            $pageItems->pluck('correlation_id')->filter()->unique()->values(),
            $companyId,
        );
        $contextByRoutineId = $this->routineContextByIds(
            $this->routineIdsFromMetadata($pageItems),
            $companyId,
        );

        $entries->through(fn (AuditEntry $entry) => $this->presentEntry(
            $entry,
            $contextByCorrelation,
            $contextByRoutineId,
        ));

        return response()->json($entries);
    }

    public function threads(Request $request): JsonResponse
    {
        $this->authorizeViewer($request);

        $companyId = app(CurrentCompany::class)->id();
        $perPage = max(1, min(50, (int) $request->query('per_page', 20)));

        $base = AuditEntry::query()
            ->tap(fn (Builder $q) => $this->constrainVisibleToCompany($q, $companyId))
            ->whereNotNull('correlation_id')
            ->when($request->query('actor_user_id'), fn ($q, $actorId) => $q->where('actor_user_id', (int) $actorId))
            ->when($request->query('access_channel'), function ($q, $channel) {
                $q->where('metadata->access_channel', $channel);
            })
            ->when($request->query('routine_id'), function ($q, $routineId) use ($companyId) {
                $this->constrainToRoutine($q, (int) $routineId, $companyId);
            })
            ->when($request->filled('q'), function ($q) use ($request, $companyId) {
                $this->constrainBySearch($q, trim((string) $request->query('q')), $companyId);
            });

        $aggregated = (clone $base)
            ->selectRaw('correlation_id, COUNT(*) as events_count, MAX(occurred_at) as last_occurred_at, MIN(occurred_at) as first_occurred_at, MAX(id) as last_entry_id')
            ->groupBy('correlation_id')
            ->orderByDesc('last_occurred_at');

        $page = max(1, (int) $request->query('page', 1));
        $total = (clone $aggregated)->get()->count();
        $rows = (clone $aggregated)
            ->forPage($page, $perPage)
            ->get();

        $correlationIds = $rows->pluck('correlation_id')->filter()->values();
        $contextByCorrelation = $this->routineContextByCorrelation($correlationIds, $companyId);

        $lastEntries = AuditEntry::query()
            ->with('actor:id,name,email')
            ->whereIn('id', $rows->pluck('last_entry_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $data = $rows->map(function ($row) use ($contextByCorrelation, $lastEntries) {
            $correlationId = (string) $row->correlation_id;
            $last = $lastEntries->get($row->last_entry_id);
            $routine = $contextByCorrelation[$correlationId] ?? null;

            return [
                'correlation_id' => $correlationId,
                'events_count' => (int) $row->events_count,
                'first_occurred_at' => $row->first_occurred_at,
                'last_occurred_at' => $row->last_occurred_at,
                'routine' => $routine,
                'last_action' => $last?->action,
                'last_actor' => $last?->actor
                    ? [
                        'id' => $last->actor->id,
                        'name' => $last->actor->name,
                        'email' => $last->actor->email,
                    ]
                    : null,
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    /**
     * Eventos de la empresa + accesos (login/logout) de miembros + acciones de plataforma sobre el tenant.
     *
     * @param  Builder<\App\Models\AuditEntry>  $query
     */
    private function constrainVisibleToCompany(Builder $query, int $companyId): void
    {
        $memberIds = CompanyMembership::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $query->where(function (Builder $scope) use ($companyId, $memberIds) {
            $scope->where('company_id', $companyId);

            if ($memberIds !== []) {
                $scope->orWhere(function (Builder $auth) use ($memberIds) {
                    $auth->whereNull('company_id')
                        ->whereIn('action', ['auth.login', 'auth.logout'])
                        ->whereIn('actor_user_id', $memberIds);
                });
            }

            $scope->orWhere(function (Builder $platform) use ($companyId) {
                $platform->whereNull('company_id')
                    ->where('subject_type', Company::class)
                    ->where('subject_id', $companyId);
            });
        });
    }

    /**
     * @param  Builder<\App\Models\AuditEntry>  $query
     */
    private function constrainToRoutine($query, int $routineId, int $companyId): void
    {
        $correlationIds = WorkflowInstance::query()
            ->whereHas('routine', fn ($r) => $r->where('company_id', $companyId)->whereKey($routineId))
            ->pluck('correlation_id');

        $query->where(function ($inner) use ($routineId, $correlationIds) {
            $inner->whereIn('correlation_id', $correlationIds)
                ->orWhere(function ($q) use ($routineId) {
                    $q->where('subject_type', Routine::class)
                        ->where('subject_id', $routineId);
                })
                ->orWhere('metadata->routine_id', $routineId);
        });
    }

    /**
     * @param  Builder<\App\Models\AuditEntry>  $query
     */
    private function constrainBySearch($query, string $q, int $companyId): void
    {
        if ($q === '') {
            return;
        }

        $matchingCorrelations = WorkflowInstance::query()
            ->whereHas('routine', function ($routine) use ($q, $companyId) {
                $routine->where('company_id', $companyId)
                    ->where(function ($inner) use ($q) {
                        if (ctype_digit($q)) {
                            $inner->whereKey((int) $q);
                        }
                        $inner->orWhereHas('asset', fn ($a) => $a->where('tag', 'like', '%'.$q.'%'))
                            ->orWhereHas('routineType', fn ($t) => $t->where('name', 'like', '%'.$q.'%'))
                            ->orWhereHas('site', fn ($s) => $s->where('name', 'like', '%'.$q.'%'));
                    });
            })
            ->pluck('correlation_id');

        $query->where(function ($inner) use ($q, $matchingCorrelations) {
            $inner->where('action', 'like', '%'.$q.'%')
                ->orWhere('correlation_id', 'like', '%'.$q.'%')
                ->orWhere('metadata->access_channel', 'like', '%'.$q.'%')
                ->orWhere('metadata->device_name', 'like', '%'.$q.'%')
                ->orWhereHas('actor', function ($actor) use ($q) {
                    $actor->where('name', 'like', '%'.$q.'%')
                        ->orWhere('email', 'like', '%'.$q.'%');
                });

            if ($matchingCorrelations->isNotEmpty()) {
                $inner->orWhereIn('correlation_id', $matchingCorrelations);
            }

            if (ctype_digit($q)) {
                $inner->orWhere(function ($subject) use ($q) {
                    $subject->where('subject_type', Routine::class)
                        ->where('subject_id', (int) $q);
                })->orWhere('metadata->routine_id', (int) $q);
            }
        });
    }

    /**
     * @param  Collection<int, AuditEntry>  $entries
     * @return Collection<int, int>
     */
    private function routineIdsFromMetadata(Collection $entries): Collection
    {
        return $entries
            ->map(function (AuditEntry $entry) {
                if (! is_array($entry->metadata) || ! isset($entry->metadata['routine_id'])) {
                    return null;
                }

                return (int) $entry->metadata['routine_id'];
            })
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, mixed>  $correlationIds
     * @return array<string, array<string, mixed>>
     */
    private function routineContextByCorrelation(Collection $correlationIds, int $companyId): array
    {
        if ($correlationIds->isEmpty()) {
            return [];
        }

        $instances = WorkflowInstance::query()
            ->with([
                'routine:id,company_id,status,site_id,asset_id,routine_type_id,assigned_to',
                'routine.asset:id,tag',
                'routine.site:id,name',
                'routine.routineType:id,name',
                'routine.assignee:id,name',
            ])
            ->whereIn('correlation_id', $correlationIds->all())
            ->whereHas('routine', fn ($r) => $r->where('company_id', $companyId))
            ->get();

        $map = [];
        foreach ($instances as $instance) {
            $routine = $instance->routine;
            if ($routine === null) {
                continue;
            }
            $map[(string) $instance->correlation_id] = $this->serializeRoutineContext(
                $routine,
                $instance->status,
                $instance->current_step_key,
            );
        }

        return $map;
    }

    /**
     * @param  Collection<int, int>  $routineIds
     * @return array<int, array<string, mixed>>
     */
    private function routineContextByIds(Collection $routineIds, int $companyId): array
    {
        if ($routineIds->isEmpty()) {
            return [];
        }

        return Routine::query()
            ->with(['asset:id,tag', 'site:id,name', 'routineType:id,name', 'assignee:id,name'])
            ->where('company_id', $companyId)
            ->whereIn('id', $routineIds->all())
            ->get()
            ->mapWithKeys(fn (Routine $routine) => [
                $routine->id => $this->serializeRoutineContext($routine),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRoutineContext(
        Routine $routine,
        ?string $workflowStatus = null,
        ?string $currentStepKey = null,
    ): array {
        return [
            'id' => $routine->id,
            'status' => $routine->status instanceof \BackedEnum
                ? $routine->status->value
                : (string) $routine->status,
            'asset_tag' => $routine->asset?->tag,
            'site_name' => $routine->site?->name,
            'routine_type_name' => $routine->routineType?->name,
            'assignee_name' => $routine->assignee?->name,
            'workflow_status' => $workflowStatus,
            'current_step_key' => $currentStepKey,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $contextByCorrelation
     * @param  array<int, array<string, mixed>>  $contextByRoutineId
     * @return array<string, mixed>
     */
    private function presentEntry(
        AuditEntry $entry,
        array $contextByCorrelation,
        array $contextByRoutineId = [],
    ): array {
        $correlationId = $entry->correlation_id ? (string) $entry->correlation_id : null;
        $routine = $correlationId ? ($contextByCorrelation[$correlationId] ?? null) : null;

        if ($routine === null && is_array($entry->metadata) && isset($entry->metadata['routine_id'])) {
            $routine = $contextByRoutineId[(int) $entry->metadata['routine_id']] ?? null;
        }

        $subjectClass = $entry->subject_type ? class_basename($entry->subject_type) : null;
        $metadata = is_array($entry->metadata) ? $entry->metadata : [];
        $channel = isset($metadata['access_channel']) && is_string($metadata['access_channel'])
            ? $metadata['access_channel']
            : null;

        return [
            'id' => $entry->id,
            'correlation_id' => $entry->correlation_id,
            'action' => $entry->action,
            'subject_type' => $entry->subject_type,
            'subject_type_label' => $subjectClass,
            'subject_id' => $entry->subject_id,
            'metadata' => $entry->metadata,
            'access_channel' => $channel,
            'access_channel_label' => $channel ? AccessChannel::label($channel) : null,
            'device_name' => isset($metadata['device_name']) && is_string($metadata['device_name'])
                ? $metadata['device_name']
                : null,
            'ip' => $entry->ip,
            'occurred_at' => $entry->occurred_at,
            'actor' => $entry->actor
                ? [
                    'id' => $entry->actor->id,
                    'name' => $entry->actor->name,
                    'email' => $entry->actor->email,
                ]
                : null,
            'routine' => $routine,
        ];
    }

    private function authorizeViewer(Request $request): void
    {
        $membership = $request->attributes->get('membership');
        $role = $membership->role;
        $roleValue = $role instanceof MembershipRole ? $role->value : (string) $role;

        if (! in_array($roleValue, [
            MembershipRole::Administrator->value,
            MembershipRole::Auditor->value,
            MembershipRole::Supervisor->value,
        ], true)) {
            abort(403, 'Audit view not allowed.');
        }
    }
}
