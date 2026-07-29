<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import { auditActionLabel } from '@/lib/auditLabels';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import AppButton from '@/components/ui/AppButton.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';
import ConfigurableDataTable from '@/components/ui/ConfigurableDataTable.vue';
import { tableActionsColumn, type TableColumnDef } from '@/lib/tableColumns';

type Actor = { id: number; name: string; email: string };

type RoutineContext = {
    id: number;
    status: string;
    asset_tag?: string | null;
    site_name?: string | null;
    routine_type_name?: string | null;
    assignee_name?: string | null;
    workflow_status?: string | null;
    current_step_key?: string | null;
};

type Entry = {
    id: number;
    action: string;
    correlation_id?: string | null;
    subject_type?: string | null;
    subject_type_label?: string | null;
    subject_id?: number | null;
    metadata?: Record<string, unknown> | null;
    ip?: string | null;
    occurred_at: string;
    actor?: Actor | null;
    routine?: RoutineContext | null;
};

type Thread = {
    correlation_id: string;
    events_count: number;
    first_occurred_at: string;
    last_occurred_at: string;
    routine?: RoutineContext | null;
    last_action?: string | null;
    last_actor?: Actor | null;
};

type ViewMode = 'threads' | 'feed';

const toast = useToast();
const route = useRoute();
const router = useRouter();

const viewMode = ref<ViewMode>((route.query.view as ViewMode) === 'feed' ? 'feed' : 'threads');
const search = ref((route.query.q as string) ?? '');
const loading = ref(true);
const detailLoading = ref(false);

const threads = ref<Thread[]>([]);
const feed = ref<Entry[]>([]);

const auditFeedTableColumns: TableColumnDef[] = [
    { id: 'when', label: 'Cuándo', cellClass: 'text-portal-muted py-2 text-xs whitespace-nowrap' },
    { id: 'routine', label: 'Rutina', cellClass: 'py-2' },
    { id: 'action', label: 'Acción', cellClass: 'py-2' },
    { id: 'actor', label: 'Actor', cellClass: 'text-portal-heading py-2 text-sm' },
    tableActionsColumn({ cellClass: 'py-2 text-right' }),
];
const selectedCorrelationId = ref<string | null>((route.query.correlation as string) ?? null);
const selectedEntries = ref<Entry[]>([]);
const expandedEntryIds = ref<Set<number>>(new Set());

const selectedThread = computed(() =>
    threads.value.find((t) => t.correlation_id === selectedCorrelationId.value) ?? null,
);

const selectedRoutine = computed(
    () => selectedThread.value?.routine ?? selectedEntries.value.find((e) => e.routine)?.routine ?? null,
);

function formatWhen(value: string): string {
    return new Date(value).toLocaleString();
}

function routineTitle(routine?: RoutineContext | null): string {
    if (!routine) {
        return 'Ciclo sin rutina vinculada';
    }
    const type = routine.routine_type_name ?? 'Rutina';
    return `${type} · #${routine.id}`;
}

function routineSubtitle(routine?: RoutineContext | null): string {
    if (!routine) {
        return 'Eventos correlacionados sin vínculo a workflow de rutina';
    }
    const parts = [routine.asset_tag, routine.site_name, routine.assignee_name].filter(Boolean);
    return parts.length ? parts.join(' · ') : 'Sin activo / sitio';
}

async function loadThreads() {
    loading.value = true;
    try {
        const qs = new URLSearchParams({ per_page: '30' });
        if (search.value.trim()) {
            qs.set('q', search.value.trim());
        }
        const res = await api<{ data: Thread[] }>(`/audit/threads?${qs}`);
        threads.value = res.data ?? [];
        if (
            selectedCorrelationId.value &&
            !threads.value.some((t) => t.correlation_id === selectedCorrelationId.value)
        ) {
            selectedCorrelationId.value = threads.value[0]?.correlation_id ?? null;
        } else if (!selectedCorrelationId.value && threads.value[0]) {
            selectedCorrelationId.value = threads.value[0].correlation_id;
        }
        if (selectedCorrelationId.value) {
            await loadThreadDetail(selectedCorrelationId.value);
        } else {
            selectedEntries.value = [];
        }
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function loadFeed() {
    loading.value = true;
    try {
        const qs = new URLSearchParams({ per_page: '50' });
        if (search.value.trim()) {
            qs.set('q', search.value.trim());
        }
        const res = await api<{ data: Entry[] }>(`/audit/entries?${qs}`);
        feed.value = res.data ?? [];
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function loadThreadDetail(correlationId: string) {
    detailLoading.value = true;
    expandedEntryIds.value = new Set();
    try {
        const res = await api<{ data: Entry[] }>(
            `/audit/entries?correlation_id=${encodeURIComponent(correlationId)}&per_page=100`,
        );
        selectedEntries.value = res.data ?? [];
    } catch (e) {
        selectedEntries.value = [];
        toast.error((e as Error).message);
    } finally {
        detailLoading.value = false;
    }
}

async function refresh() {
    if (viewMode.value === 'threads') {
        await loadThreads();
    } else {
        await loadFeed();
    }
}

function selectThread(correlationId: string) {
    selectedCorrelationId.value = correlationId;
    void router.replace({
        query: {
            ...route.query,
            view: 'threads',
            correlation: correlationId,
            q: search.value.trim() || undefined,
        },
    });
    void loadThreadDetail(correlationId);
}

function setView(mode: ViewMode) {
    viewMode.value = mode;
    void router.replace({
        query: {
            ...route.query,
            view: mode === 'feed' ? 'feed' : undefined,
            correlation: mode === 'threads' ? selectedCorrelationId.value ?? undefined : undefined,
            q: search.value.trim() || undefined,
        },
    });
    void refresh();
}

function applySearch() {
    void router.replace({
        query: {
            ...route.query,
            q: search.value.trim() || undefined,
            correlation: viewMode.value === 'threads' ? selectedCorrelationId.value ?? undefined : undefined,
        },
    });
    void refresh();
}

function toggleEntry(id: number) {
    const next = new Set(expandedEntryIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    expandedEntryIds.value = next;
}

function metadataLines(metadata: Record<string, unknown> | null | undefined): Array<{ key: string; value: string }> {
    if (!metadata || Object.keys(metadata).length === 0) {
        return [];
    }
    return Object.entries(metadata).map(([key, value]) => ({
        key,
        value: typeof value === 'string' ? value : JSON.stringify(value, null, 2),
    }));
}

watch(
    () => route.query.correlation,
    (value) => {
        if (typeof value === 'string' && value !== selectedCorrelationId.value) {
            selectedCorrelationId.value = value;
            if (viewMode.value === 'threads') {
                void loadThreadDetail(value);
            }
        }
    },
);

onMounted(() => {
    void refresh();
});
</script>

<template>
    <div class="portal-page space-y-5" data-tour="page-audit">
        <PageHeader
            title="Auditoría"
            subtitle="Trazabilidad por rutina: identifica el ciclo, abre el detalle y revisa cada evento con su contexto."
        />

        <form class="flex flex-wrap items-end gap-3" @submit.prevent="applySearch">
            <div class="min-w-[16rem] flex-1">
                <MaterialField
                    v-model="search"
                    label="Buscar rutina, activo, acción o actor"
                    placeholder="Ej. 12, L200, workflow, Ana…"
                />
            </div>
            <AppButton type="submit" variant="secondary">Buscar</AppButton>
            <div class="flex rounded-xl border border-white/10 p-1">
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm"
                    :class="
                        viewMode === 'threads'
                            ? 'bg-white/10 text-portal-heading font-medium'
                            : 'text-portal-muted hover:text-portal-heading'
                    "
                    @click="setView('threads')"
                >
                    Por rutina
                </button>
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm"
                    :class="
                        viewMode === 'feed'
                            ? 'bg-white/10 text-portal-heading font-medium'
                            : 'text-portal-muted hover:text-portal-heading'
                    "
                    @click="setView('feed')"
                >
                    Todos los eventos
                </button>
            </div>
        </form>

        <p v-if="loading" class="text-portal-muted">Cargando…</p>

        <template v-else-if="viewMode === 'threads'">
            <p v-if="threads.length === 0" class="text-portal-muted text-sm">
                No hay ciclos correlacionados con esos filtros.
            </p>
            <div v-else class="grid gap-4 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
                <div class="portal-form-panel max-h-[70vh] space-y-2 overflow-y-auto p-2">
                    <button
                        v-for="thread in threads"
                        :key="thread.correlation_id"
                        type="button"
                        class="w-full rounded-xl border px-3 py-3 text-left transition"
                        :class="
                            selectedCorrelationId === thread.correlation_id
                                ? 'border-sky-400/50 bg-sky-500/10'
                                : 'border-transparent hover:border-white/10 hover:bg-white/5'
                        "
                        @click="selectThread(thread.correlation_id)"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-portal-heading truncate text-sm font-medium">
                                    {{ routineTitle(thread.routine) }}
                                </p>
                                <p class="text-portal-muted mt-0.5 truncate text-xs">
                                    {{ routineSubtitle(thread.routine) }}
                                </p>
                            </div>
                            <StatusBadge v-if="thread.routine?.status" :status="thread.routine.status" />
                        </div>
                        <div class="text-portal-muted mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[11px]">
                            <span>{{ thread.events_count }} eventos</span>
                            <span>{{ formatWhen(thread.last_occurred_at) }}</span>
                        </div>
                        <p v-if="thread.last_action" class="text-portal-heading mt-1 truncate font-mono text-[11px]">
                            {{ auditActionLabel(thread.last_action) }}
                        </p>
                    </button>
                </div>

                <div class="portal-form-panel space-y-4 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-white/10 pb-4">
                        <div class="min-w-0 space-y-1">
                            <h2 class="text-portal-heading text-lg font-semibold">
                                {{ routineTitle(selectedRoutine) }}
                            </h2>
                            <p class="text-portal-muted text-sm">{{ routineSubtitle(selectedRoutine) }}</p>
                            <p v-if="selectedCorrelationId" class="text-portal-muted font-mono text-[11px]">
                                correlation: {{ selectedCorrelationId }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <StatusBadge v-if="selectedRoutine?.status" :status="selectedRoutine.status" />
                            <IconActionButton
                                v-if="selectedRoutine?.id"
                                icon="chevron-right"
                                label="Abrir rutina"
                                :to="`/app/routines/${selectedRoutine.id}`"
                            />
                        </div>
                    </div>

                    <p v-if="detailLoading" class="text-portal-muted text-sm">Cargando trazabilidad…</p>
                    <p v-else-if="selectedEntries.length === 0" class="text-portal-muted text-sm">
                        Sin eventos en este ciclo.
                    </p>
                    <ol v-else class="relative space-y-0 border-l border-white/15 pl-4">
                        <li
                            v-for="entry in selectedEntries"
                            :key="entry.id"
                            class="relative pb-5 last:pb-0"
                        >
                            <span
                                class="absolute -left-[1.3rem] top-1.5 h-2.5 w-2.5 rounded-full bg-sky-400 ring-4 ring-[var(--portal-panel,transparent)]"
                            />
                            <button
                                type="button"
                                class="w-full rounded-xl px-2 py-1 text-left transition hover:bg-white/5"
                                @click="toggleEntry(entry.id)"
                            >
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <p class="text-portal-heading text-sm font-medium">
                                        {{ auditActionLabel(entry.action) }}
                                    </p>
                                    <span class="text-portal-muted text-xs whitespace-nowrap">
                                        {{ formatWhen(entry.occurred_at) }}
                                    </span>
                                </div>
                                <div class="text-portal-muted mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                                    <span>{{ entry.actor?.name ?? 'Sistema / sin actor' }}</span>
                                    <span v-if="entry.subject_type_label">
                                        {{ entry.subject_type_label }}
                                        <template v-if="entry.subject_id">#{{ entry.subject_id }}</template>
                                    </span>
                                    <span v-if="entry.ip">IP {{ entry.ip }}</span>
                                    <span class="font-mono opacity-70">{{ entry.action }}</span>
                                </div>
                            </button>
                            <div
                                v-if="expandedEntryIds.has(entry.id)"
                                class="mt-2 space-y-2 rounded-xl border border-white/10 bg-black/20 p-3 text-xs"
                            >
                                <p class="text-portal-muted">
                                    Detalle del evento #{{ entry.id }}
                                </p>
                                <dl class="grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <dt class="text-portal-muted">Acción</dt>
                                        <dd class="text-portal-heading font-mono">{{ entry.action }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-portal-muted">Actor</dt>
                                        <dd class="text-portal-heading">
                                            {{ entry.actor ? `${entry.actor.name} (${entry.actor.email})` : '—' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-portal-muted">Sujeto</dt>
                                        <dd class="text-portal-heading">
                                            <template v-if="entry.subject_type">
                                                {{ entry.subject_type_label ?? entry.subject_type }}
                                                #{{ entry.subject_id }}
                                            </template>
                                            <template v-else>—</template>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-portal-muted">Correlation</dt>
                                        <dd class="text-portal-heading break-all font-mono">
                                            {{ entry.correlation_id ?? '—' }}
                                        </dd>
                                    </div>
                                </dl>
                                <div v-if="metadataLines(entry.metadata).length">
                                    <p class="text-portal-muted mb-1">Metadata</p>
                                    <dl class="space-y-1.5">
                                        <div
                                            v-for="line in metadataLines(entry.metadata)"
                                            :key="line.key"
                                            class="rounded-lg bg-white/5 px-2 py-1.5"
                                        >
                                            <dt class="text-portal-muted font-mono">{{ line.key }}</dt>
                                            <dd class="text-portal-heading whitespace-pre-wrap break-all font-mono">
                                                {{ line.value }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                                <p v-else class="text-portal-muted">Sin metadata adicional.</p>
                            </div>
                        </li>
                    </ol>
                </div>
            </div>
        </template>

        <template v-else>
            <ConfigurableDataTable
                v-if="feed.length > 0"
                table-id="audit-feed"
                :columns="auditFeedTableColumns"
                :rows="feed"
                row-key="id"
            >
                <template #when="{ row }">{{ formatWhen((row as Entry).occurred_at) }}</template>
                <template #routine="{ row }">
                    <div v-if="(row as Entry).routine" class="space-y-0.5">
                        <RouterLink
                            class="text-portal-link text-sm font-medium hover:underline"
                            :to="`/app/routines/${(row as Entry).routine!.id}`"
                        >
                            {{ routineTitle((row as Entry).routine!) }}
                        </RouterLink>
                        <p class="text-portal-muted text-xs">{{ routineSubtitle((row as Entry).routine!) }}</p>
                    </div>
                    <span v-else class="text-portal-muted text-xs">—</span>
                </template>
                <template #action="{ row }">
                    <p class="text-portal-heading text-sm">{{ auditActionLabel((row as Entry).action) }}</p>
                    <p class="text-portal-muted font-mono text-[11px]">{{ (row as Entry).action }}</p>
                </template>
                <template #actor="{ row }">{{ (row as Entry).actor?.name ?? '—' }}</template>
                <template #actions="{ row }">
                    <IconActionButton
                        v-if="(row as Entry).correlation_id"
                        icon="eye"
                        label="Ver ciclo de auditoría"
                        @click="
                            viewMode = 'threads';
                            selectThread((row as Entry).correlation_id!);
                        "
                    />
                </template>
            </ConfigurableDataTable>
            <p v-else class="text-portal-muted text-sm">Sin registros recientes.</p>
        </template>
    </div>
</template>
