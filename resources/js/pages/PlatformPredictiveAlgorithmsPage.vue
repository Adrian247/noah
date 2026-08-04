<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api, getCompanyId, getToken } from '@/api/client';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppModal from '@/components/ui/AppModal.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import ConfigurableDataTable from '@/components/ui/ConfigurableDataTable.vue';
import type { TableColumnDef } from '@/lib/tableColumns';

type AlgorithmVersion = {
    id: number;
    semver: string;
    status: string;
    kind: string;
    kind_label?: string;
    notes: string | null;
    metrics?: { roc_auc?: number | null; rows?: number | null } | null;
    regression_report?: { roc_auc?: number | null; rows?: number | null } | null;
    training_summary: {
        companies_opted_in?: number;
        validated_routines?: number;
        validated_services?: number;
        documents_used?: number;
        document_records?: number;
        note?: string;
    } | null;
    published_at: string | null;
    created_at: string | null;
};

type TrainingDoc = {
    id: number;
    kind: string;
    kind_label: string;
    name: string;
    original_filename: string;
    record_count: number;
    status: string;
    validation_errors?: string[] | null;
};

type FieldGuide = { name: string; required: boolean; description: string };

type SchemaInfo = {
    kind: string;
    label: string;
    description: string;
    csv_headers: string[];
    fields?: FieldGuide[];
    json_example: Record<string, unknown>;
};

type GuideInfo = {
    title: string;
    summary: string;
    steps: string[];
    documents: {
        optional: boolean;
        formats: string[];
        contract: string;
        note: string;
    };
    regression: {
        what: string;
        metric: string;
        requires: string;
        not_a_document: boolean;
    };
};

type CorpusKind = {
    kind: string;
    kind_label: string;
    validated_services: number;
    assets_covered?: number | null;
    clients_covered?: number | null;
    documents_ready: number;
    document_records: number;
    volume_level: string;
    volume_label: string;
    volume_hint: string;
};

type CorpusInfo = {
    note: string;
    overall_volume_level: string;
    overall_volume_label: string;
    overall_volume_hint: string;
    ready_documents: number;
    opt_in: {
        companies_count: number;
        companies: Array<{ id: number; name: string }>;
        reminder: string;
    };
    kinds: CorpusKind[];
};

const toast = useToast();
const confirm = useConfirm();
const loading = ref(true);
const training = ref(false);
const versions = ref<AlgorithmVersion[]>([]);
const documents = ref<TrainingDoc[]>([]);
const schemas = ref<SchemaInfo[]>([]);
const guide = ref<GuideInfo | null>(null);
const corpus = ref<CorpusInfo | null>(null);
const notes = ref('');
const kind = ref('maintenance_hazard_v2');
const selectedDocIds = ref<number[]>([]);
const uploading = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const showGuide = ref(true);
const editingVersion = ref<AlgorithmVersion | null>(null);
const editNotes = ref('');
const savingNotes = ref(false);

const kindOptions = computed(() =>
    schemas.value.map((s) => ({ value: s.kind, label: s.label })),
);

const readyDocsForKind = computed(() =>
    documents.value.filter((d) => d.kind === kind.value && d.status === 'ready'),
);

const activeSchema = computed(() => schemas.value.find((s) => s.kind === kind.value) ?? null);

const activeCorpusKind = computed(
    () => corpus.value?.kinds.find((k) => k.kind === kind.value) ?? null,
);

function volumeBadgeClass(level: string): string {
    if (level === 'strong') return 'border-[color:var(--portal-border)] bg-[color:color-mix(in_srgb,var(--portal-success,#22c55e)_18%,transparent)] text-portal-heading';
    if (level === 'adequate') return 'border-[color:var(--portal-border)] bg-[color:color-mix(in_srgb,var(--portal-accent,#38bdf8)_16%,transparent)] text-portal-heading';
    if (level === 'limited') return 'border-[color:var(--portal-border)] bg-[color:color-mix(in_srgb,#f59e0b_16%,transparent)] text-portal-heading';
    return 'border-[color:var(--portal-border)] bg-[color:color-mix(in_srgb,#f43f5e_14%,transparent)] text-portal-heading';
}

const columns: TableColumnDef[] = [
    { id: 'semver', label: 'Versión' },
    { id: 'status', label: 'Estado' },
    { id: 'training', label: 'Entrenamiento' },
    { id: 'regression', label: 'Regresión' },
    { id: 'notes', label: 'Notas' },
    { id: 'actions', label: 'Acciones', locked: true },
];

const docColumns: TableColumnDef[] = [
    { id: 'name', label: 'Documento' },
    { id: 'kind_label', label: 'Algoritmo' },
    { id: 'record_count', label: 'Registros' },
    { id: 'status', label: 'Estado' },
    { id: 'actions', label: 'Acciones', locked: true },
];

function statusBadge(status: string): string {
    if (status === 'published') return 'validated';
    if (status === 'draft') return 'pending_validation';
    return 'inactive';
}

async function load() {
    loading.value = true;
    try {
        const [verRes, docRes, schemaRes, corpusRes] = await Promise.all([
            api<{ data: AlgorithmVersion[] }>('/platform/predictive/algorithms'),
            api<{ data: TrainingDoc[] }>('/platform/predictive/training-documents'),
            api<{ data: SchemaInfo[]; guide: GuideInfo }>('/platform/predictive/training-documents/schemas'),
            api<{ data: CorpusInfo }>('/platform/predictive/algorithms/corpus'),
        ]);
        versions.value = verRes.data;
        documents.value = docRes.data;
        schemas.value = schemaRes.data;
        guide.value = schemaRes.guide;
        corpus.value = corpusRes.data;
        if (!schemas.value.some((s) => s.kind === kind.value) && schemas.value[0]) {
            kind.value = schemas.value[0].kind;
        }
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function downloadTemplate(format: 'json' | 'csv') {
    try {
        const headers: Record<string, string> = { Accept: '*/*' };
        const token = getToken();
        if (token) headers.Authorization = `Bearer ${token}`;
        const companyId = getCompanyId();
        if (companyId) headers['X-Company-Id'] = companyId;

        const res = await fetch(
            `/api/v1/platform/predictive/training-documents/templates/${encodeURIComponent(kind.value)}?format=${format}`,
            { headers },
        );
        if (!res.ok) {
            throw new Error(`No se pudo descargar la plantilla (${res.status}).`);
        }
        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${kind.value}.${format}`;
        a.click();
        URL.revokeObjectURL(url);
        toast.success(`Plantilla ${format.toUpperCase()} descargada.`);
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function train() {
    training.value = true;
    try {
        await api('/platform/predictive/algorithms/train', {
            method: 'POST',
            body: JSON.stringify({
                notes: notes.value || null,
                kind: kind.value,
                document_ids: selectedDocIds.value,
                run_regression: true,
            }),
        });
        toast.success('Versión draft entrenada con regresión. Revísala y publícala.');
        notes.value = '';
        selectedDocIds.value = [];
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        training.value = false;
    }
}

async function runRegression(row: AlgorithmVersion) {
    try {
        const res = await api<{ data: { roc_auc?: number | null; rows?: number } }>(
            `/platform/predictive/algorithms/${row.id}/regression`,
            { method: 'POST' },
        );
        toast.success(
            `Regresión: AUC ${res.data.roc_auc ?? 'n/d'} · ${res.data.rows ?? 0} filas.`,
        );
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function publish(row: AlgorithmVersion) {
    try {
        await api(`/platform/predictive/algorithms/${row.id}/publish`, { method: 'POST' });
        toast.success(`Publicada v${row.semver}.`);
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function archive(row: AlgorithmVersion) {
    try {
        await api(`/platform/predictive/algorithms/${row.id}/archive`, { method: 'POST' });
        toast.success(`Archivada v${row.semver}.`);
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

function openEditNotes(row: AlgorithmVersion) {
    editingVersion.value = row;
    editNotes.value = row.notes ?? '';
}

function closeEditNotes() {
    editingVersion.value = null;
    editNotes.value = '';
}

async function saveNotes() {
    if (!editingVersion.value) {
        return;
    }
    savingNotes.value = true;
    try {
        await api(`/platform/predictive/algorithms/${editingVersion.value.id}`, {
            method: 'PATCH',
            body: JSON.stringify({ notes: editNotes.value.trim() || null }),
        });
        toast.success(`Notas de v${editingVersion.value.semver} actualizadas.`);
        closeEditNotes();
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        savingNotes.value = false;
    }
}

function toggleDoc(id: number) {
    const set = new Set(selectedDocIds.value);
    if (set.has(id)) {
        set.delete(id);
    } else {
        set.add(id);
    }
    selectedDocIds.value = [...set];
}

async function onFileSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
        return;
    }
    uploading.value = true;
    try {
        const body = new FormData();
        body.append('file', file);
        body.append('kind', kind.value);
        await api('/platform/predictive/training-documents', { method: 'POST', body });
        toast.success('Documento cargado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        uploading.value = false;
        input.value = '';
    }
}

async function deleteDoc(row: TrainingDoc) {
    const accepted = await confirm(
        `¿Eliminar el documento «${row.name}»? Esta acción no se puede deshacer.`,
        { title: 'Eliminar documento de entrenamiento', confirmLabel: 'Eliminar', danger: true },
    );
    if (!accepted) {
        return;
    }
    try {
        await api(`/platform/predictive/training-documents/${row.id}`, { method: 'DELETE' });
        toast.success('Documento eliminado.');
        selectedDocIds.value = selectedDocIds.value.filter((id) => id !== row.id);
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

onMounted(load);
</script>

<template>
    <div class="portal-page">
        <PageHeader
            title="Algoritmos predictivos"
            subtitle="Entrena (solo root) con historial de servicios de empresas con opt-in y documentos opcionales. Valida precisión con regresión."
        />

        <section
            v-if="corpus"
            class="mb-6 rounded-2xl border border-[color:var(--portal-border)] bg-[color:var(--portal-surface)] p-4"
        >
            <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <h2 class="text-portal-heading text-sm font-semibold uppercase tracking-wide">
                        Información disponible para entrenamiento
                    </h2>
                    <p class="text-portal-muted mt-2 text-sm leading-relaxed">{{ corpus.note }}</p>
                    <p class="text-portal-muted mt-2 text-xs leading-relaxed">{{ corpus.opt_in.reminder }}</p>
                </div>
                <span
                    class="rounded-full border px-3 py-1 text-xs font-medium"
                    :class="volumeBadgeClass(corpus.overall_volume_level)"
                >
                    {{ corpus.overall_volume_label }}
                </span>
            </div>
            <p class="text-portal-muted mb-3 text-xs">{{ corpus.overall_volume_hint }}</p>
            <div class="grid gap-3 md:grid-cols-3">
                <div
                    v-for="item in corpus.kinds"
                    :key="item.kind"
                    class="rounded-xl border border-[color:var(--portal-border)] p-3"
                    :class="item.kind === kind ? 'ring-1 ring-sky-400/40' : ''"
                >
                    <div class="mb-1 flex items-start justify-between gap-2">
                        <p class="text-portal-heading text-sm font-medium">{{ item.kind_label }}</p>
                        <span
                            class="rounded-full border px-2 py-0.5 text-[10px] font-medium"
                            :class="volumeBadgeClass(item.volume_level)"
                        >
                            {{ item.volume_label }}
                        </span>
                    </div>
                    <p class="text-portal-muted text-xs leading-relaxed">
                        {{ item.validated_services }} servicios
                        <template v-if="item.assets_covered != null">
                            · {{ item.assets_covered }} activos
                        </template>
                        <template v-if="item.clients_covered != null">
                            · {{ item.clients_covered }} clientes
                        </template>
                        · {{ item.documents_ready }} docs ({{ item.document_records }} regs.)
                    </p>
                    <p class="text-portal-muted mt-1 text-[11px] leading-relaxed">{{ item.volume_hint }}</p>
                </div>
            </div>
            <p v-if="corpus.opt_in.companies.length" class="text-portal-muted mt-3 text-xs">
                Empresas con opt-in:
                {{ corpus.opt_in.companies.map((c) => c.name).join(' · ') }}
            </p>
        </section>

        <section
            v-if="guide"
            class="mb-6 rounded-2xl border border-[color:var(--portal-border)] bg-[color:var(--portal-surface)] p-4"
        >
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-portal-heading text-sm font-semibold uppercase tracking-wide">
                    {{ guide.title }}
                </h2>
                <AppButton type="button" variant="secondary" @click="showGuide = !showGuide">
                    {{ showGuide ? 'Ocultar guía' : 'Mostrar guía' }}
                </AppButton>
            </div>
            <div v-if="showGuide" class="space-y-4 text-sm leading-relaxed">
                <p class="text-portal-muted">{{ guide.summary }}</p>
                <ol class="text-portal-muted list-decimal space-y-1 pl-5">
                    <li v-for="(step, i) in guide.steps" :key="i">{{ step }}</li>
                </ol>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-xl border border-[color:var(--portal-border)] p-3">
                        <h3 class="text-portal-heading mb-1 text-xs font-semibold uppercase tracking-wide">
                            Documentos de entrenamiento
                        </h3>
                        <p class="text-portal-muted">{{ guide.documents.note }}</p>
                        <p class="text-portal-muted mt-2 text-xs">
                            Contrato
                            <code class="font-mono">{{ guide.documents.contract }}</code>
                            · formatos {{ guide.documents.formats.join(' / ').toUpperCase() }}
                            · {{ guide.documents.optional ? 'opcionales' : 'obligatorios' }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-[color:var(--portal-border)] p-3">
                        <h3 class="text-portal-heading mb-1 text-xs font-semibold uppercase tracking-wide">
                            Regresión (no es un archivo)
                        </h3>
                        <p class="text-portal-muted">{{ guide.regression.what }}</p>
                        <p class="text-portal-muted mt-2">{{ guide.regression.metric }}</p>
                        <p class="text-portal-muted mt-2 text-xs">{{ guide.regression.requires }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section
            class="mb-6 rounded-2xl border border-[color:var(--portal-border)] bg-[color:var(--portal-surface)] p-4"
        >
            <h2 class="text-portal-heading mb-2 text-sm font-semibold uppercase tracking-wide">
                Documentos de entrenamiento
            </h2>
            <p class="text-portal-muted mb-3 text-sm leading-relaxed">
                Descarga la plantilla del algoritmo elegido, sustituye los códigos de ejemplo por los de tu
                tenant y súbela. No hace falta documento para correr la regresión.
            </p>
            <div class="mb-3 grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
                <MaterialSelect v-model="kind" label="Algoritmo / formato" :options="kindOptions" />
                <div class="flex flex-wrap gap-2">
                    <AppButton type="button" variant="secondary" @click="downloadTemplate('json')">
                        Plantilla JSON
                    </AppButton>
                    <AppButton type="button" variant="secondary" @click="downloadTemplate('csv')">
                        Plantilla CSV
                    </AppButton>
                    <input ref="fileInput" type="file" class="hidden" accept=".json,.csv,.txt" @change="onFileSelected" />
                    <AppButton type="button" variant="secondary" :disabled="uploading" @click="fileInput?.click()">
                        {{ uploading ? 'Subiendo…' : 'Subir documento' }}
                    </AppButton>
                </div>
            </div>
            <div v-if="activeSchema" class="text-portal-muted mb-4 text-xs leading-relaxed">
                <p class="text-portal-heading text-sm font-medium">{{ activeSchema.label }}</p>
                <p class="mt-1">{{ activeSchema.description }}</p>
                <ul v-if="activeSchema.fields?.length" class="mt-2 space-y-1">
                    <li v-for="field in activeSchema.fields" :key="field.name">
                        <code class="font-mono">{{ field.name }}</code>
                        <span v-if="field.required"> (requerido)</span>
                        — {{ field.description }}
                    </li>
                </ul>
                <p class="mt-2">
                    Cabeceras CSV:
                    <code class="font-mono">{{ activeSchema.csv_headers.join(', ') }}</code>
                </p>
            </div>
            <ConfigurableDataTable
                table-id="platform-predictive-docs"
                :columns="docColumns"
                :rows="documents"
                row-key="id"
                :show-export="false"
                empty-text="Sin documentos. Descarga una plantilla, edítala y súbela para enriquecer el entrenamiento."
            >
                <template #name="{ row }">
                    <span class="text-portal-heading">{{ (row as TrainingDoc).name }}</span>
                    <p class="text-portal-muted font-mono text-[11px]">{{ (row as TrainingDoc).original_filename }}</p>
                </template>
                <template #kind_label="{ row }">{{ (row as TrainingDoc).kind_label }}</template>
                <template #record_count="{ row }">{{ (row as TrainingDoc).record_count }}</template>
                <template #status="{ row }">
                    <span class="text-xs">{{ (row as TrainingDoc).status }}</span>
                    <p
                        v-if="(row as TrainingDoc).validation_errors?.length"
                        class="text-portal-muted text-[11px]"
                    >
                        {{ (row as TrainingDoc).validation_errors?.[0] }}
                    </p>
                </template>
                <template #actions="{ row }">
                    <AppButton type="button" variant="secondary" @click="deleteDoc(row as TrainingDoc)">
                        Eliminar
                    </AppButton>
                </template>
            </ConfigurableDataTable>
        </section>

        <section
            class="mb-6 rounded-2xl border border-[color:var(--portal-border)] bg-[color:var(--portal-surface)] p-4"
        >
            <h2 class="text-portal-heading mb-2 text-sm font-semibold uppercase tracking-wide">
                Nuevo entrenamiento
            </h2>
            <p class="text-portal-muted mb-4 text-sm leading-relaxed">
                Usa historial validado de servicios de empresas con opt-in + documentos ready del algoritmo elegido.
                Genera borrador y corre regresión automática de precisión.
                <template v-if="activeCorpusKind">
                    <span class="text-portal-heading"> {{ activeCorpusKind.volume_label }}.</span>
                </template>
            </p>
            <div class="mb-3 grid gap-3 md:grid-cols-2">
                <MaterialSelect v-model="kind" label="Familia de algoritmo" :options="kindOptions" />
                <MaterialField v-model="notes" label="Notas (opcional)" />
            </div>
            <fieldset v-if="readyDocsForKind.length" class="mb-4 space-y-2">
                <legend class="text-portal-heading text-xs font-medium">Documentos a incluir</legend>
                <label
                    v-for="doc in readyDocsForKind"
                    :key="doc.id"
                    class="text-portal-muted flex items-center gap-2 text-sm"
                >
                    <input
                        type="checkbox"
                        :checked="selectedDocIds.includes(doc.id)"
                        @change="toggleDoc(doc.id)"
                    />
                    {{ doc.name }} ({{ doc.record_count }} regs.)
                </label>
            </fieldset>
            <AppButton type="button" :disabled="training" @click="train">
                {{ training ? 'Entrenando…' : 'Entrenar nueva versión' }}
            </AppButton>
        </section>

        <ConfigurableDataTable
            table-id="platform-predictive-algorithms"
            :columns="columns"
            :rows="versions"
            row-key="id"
            :show-export="false"
            empty-text="Aún no hay versiones. Entrena la primera."
        >
            <template #semver="{ row }">
                <span class="font-mono font-semibold text-portal-heading">v{{ (row as AlgorithmVersion).semver }}</span>
                <p class="text-portal-muted text-xs">
                    {{ (row as AlgorithmVersion).kind_label || (row as AlgorithmVersion).kind }}
                </p>
            </template>
            <template #status="{ row }">
                <StatusBadge :status="statusBadge((row as AlgorithmVersion).status)" />
                <span class="text-portal-muted ml-1 text-xs">{{ (row as AlgorithmVersion).status }}</span>
            </template>
            <template #training="{ row }">
                <span class="text-sm text-portal-heading">
                    {{ (row as AlgorithmVersion).training_summary?.validated_services
                        ?? (row as AlgorithmVersion).training_summary?.validated_routines
                        ?? 0 }}
                    servicios ·
                    {{ (row as AlgorithmVersion).training_summary?.companies_opted_in ?? 0 }} empresas ·
                    {{ (row as AlgorithmVersion).training_summary?.documents_used ?? 0 }} docs
                </span>
            </template>
            <template #regression="{ row }">
                <span class="text-sm text-portal-heading">
                    AUC
                    {{
                        (row as AlgorithmVersion).regression_report?.roc_auc
                            ?? (row as AlgorithmVersion).metrics?.roc_auc
                            ?? '—'
                    }}
                    ·
                    {{
                        (row as AlgorithmVersion).regression_report?.rows
                            ?? (row as AlgorithmVersion).metrics?.rows
                            ?? 0
                    }}
                    filas
                </span>
            </template>
            <template #notes="{ row }">
                <div class="flex max-w-xs flex-col gap-1">
                    <span class="text-portal-muted line-clamp-2 text-sm">
                        {{ (row as AlgorithmVersion).notes || '—' }}
                    </span>
                    <button
                        type="button"
                        class="text-left text-xs text-sky-500 underline-offset-2 hover:underline"
                        @click.stop="openEditNotes(row as AlgorithmVersion)"
                    >
                        {{ (row as AlgorithmVersion).notes ? 'Editar nota' : 'Agregar nota' }}
                    </button>
                </div>
            </template>
            <template #actions="{ row }">
                <div class="flex flex-wrap gap-2" @click.stop>
                    <AppButton type="button" variant="secondary" @click="runRegression(row as AlgorithmVersion)">
                        Regresión
                    </AppButton>
                    <AppButton
                        v-if="(row as AlgorithmVersion).status === 'draft'"
                        type="button"
                        variant="primary"
                        @click="publish(row as AlgorithmVersion)"
                    >
                        Publicar
                    </AppButton>
                    <AppButton
                        v-if="(row as AlgorithmVersion).status === 'published'"
                        type="button"
                        variant="secondary"
                        @click="archive(row as AlgorithmVersion)"
                    >
                        Archivar
                    </AppButton>
                </div>
            </template>
        </ConfigurableDataTable>

        <AppModal
            :open="editingVersion !== null"
            :title="editingVersion ? `Notas · v${editingVersion.semver}` : 'Notas'"
            size="sm"
            @close="closeEditNotes"
        >
            <MaterialField
                v-model="editNotes"
                label="Notas (opcional)"
                multiline
                :rows="4"
                placeholder="Ej. Corpus SMA + Planta 4400; regla F=paro por falla"
            />
            <template #footer>
                <AppButton type="button" variant="secondary" :disabled="savingNotes" @click="closeEditNotes">
                    Cancelar
                </AppButton>
                <AppButton type="button" :disabled="savingNotes" @click="saveNotes">
                    {{ savingNotes ? 'Guardando…' : 'Guardar' }}
                </AppButton>
            </template>
        </AppModal>
    </div>
</template>
