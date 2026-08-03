<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useToast } from '@/composables/useToast';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import SectionSubnav from '@/components/ui/SectionSubnav.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import AppButton from '@/components/ui/AppButton.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';
import ConfigurableDataTable from '@/components/ui/ConfigurableDataTable.vue';
import { tableActionsColumn, type TableColumnDef } from '@/lib/tableColumns';
import { routinesSectionNav } from '@/lib/sectionNav';

type WorkflowDef = { id: number; name: string; status?: string };
type VersionOption = { id: number; version: number };
type FormCatalog = { id: number; name: string; published_version?: VersionOption | null };
type ReportCatalog = {
    id: number;
    name: string;
    published_version?: VersionOption | null;
    draft_version?: VersionOption | null;
};

type RoutineType = {
    id: number;
    name: string;
    slug: string;
    service_line?: string;
    is_active: boolean;
    workflow_definition_id?: number | null;
    form_version_id?: number | null;
    report_template_version_id?: number | null;
    form_version?: { id: number; version: number; definition?: { name: string } } | null;
    report_template_version?: { id: number; version: number; template?: { name: string } } | null;
    workflow_definition?: WorkflowDef | null;
    field_alignment?: {
        aligned: boolean;
        missing: string[];
        missing_images: string[];
        checked: boolean;
    };
};

const SERVICE_LINE_OPTIONS = [
    { value: 'maintenance', label: 'Mantenimiento' },
    { value: 'fabrication', label: 'Manufactura' },
    { value: 'supply', label: 'Suministro' },
];

const SERVICE_LINE_LABELS: Record<string, string> = {
    maintenance: 'Mantenimiento',
    fabrication: 'Manufactura',
    supply: 'Suministro',
};

const { canWriteModule } = useModuleAccess();
const toast = useToast();
const canWrite = computed(() => canWriteModule('design_routine_types'));
const canLinkForm = computed(
    () => canWriteModule('design_forms') || canWriteModule('design_routine_types'),
);
const canLinkReport = computed(
    () => canWriteModule('design_reports') || canWriteModule('design_routine_types'),
);
const canAssignWorkflow = computed(() => canWriteModule('design_workflows'));

const routineTypeTableColumns = computed((): TableColumnDef[] => {
    const cols: TableColumnDef[] = [
        { id: 'name', label: 'Nombre', cellClass: 'py-3 pr-2' },
        { id: 'service_line', label: 'Línea de servicio', cellClass: 'py-3' },
        { id: 'status', label: 'Estado', cellClass: 'py-3' },
        { id: 'form', label: 'Formulario (publicado)', cellClass: 'max-w-xs py-3 pr-2' },
        { id: 'report', label: 'Reporte (publicado)', cellClass: 'max-w-xs py-3 pr-2' },
        { id: 'workflow', label: 'Workflow', cellClass: 'max-w-[10rem] py-3' },
    ];
    if (canWrite.value) {
        cols.push(tableActionsColumn({ label: 'Acciones', headerClass: 'py-2', cellClass: 'py-3' }));
    }
    return cols;
});

const types = ref<RoutineType[]>([]);
const workflows = ref<WorkflowDef[]>([]);
const formCatalog = ref<FormCatalog[]>([]);
const reportCatalog = ref<ReportCatalog[]>([]);
const loading = ref(true);

const showCreate = ref(false);
const newName = ref('');
const newSlug = ref('');
const newServiceLine = ref('maintenance');
const creating = ref(false);

const emptyOption = { value: '', label: '— Sin enlazar —' };
const workflowEmptyOption = { value: '', label: '—' };

const formOptions = computed(() =>
    formCatalog.value
        .filter((f) => f.published_version)
        .map((f) => ({
            value: String(f.published_version!.id),
            label: `${f.name} · v${f.published_version!.version}`,
        })),
);

const reportOptions = computed(() =>
    reportCatalog.value
        .filter((r) => r.published_version)
        .map((r) => ({
            value: String(r.published_version!.id),
            label: `${r.name} · v${r.published_version!.version}`,
        })),
);

const unpublishedReportNames = computed(() =>
    reportCatalog.value
        .filter((r) => !r.published_version && r.draft_version)
        .map((r) => r.name),
);

const workflowOptions = computed(() =>
    workflows.value.map((w) => ({
        value: String(w.id),
        label: w.status && w.status !== 'published' ? `${w.name} (borrador)` : w.name,
    })),
);

function formSelectOptions() {
    return [emptyOption, ...formOptions.value];
}

function reportSelectOptions() {
    return [emptyOption, ...reportOptions.value];
}

function workflowSelectOptions() {
    return [workflowEmptyOption, ...workflowOptions.value];
}

async function load() {
    loading.value = true;
    try {
        const typesRes = await api<{ data: RoutineType[] }>('/routine-types?all=1');
        const [formsRes, reportsRes] = await Promise.all([
            api<{ data: FormCatalog[] }>('/design/forms?usage=routine'),
            api<{ data: ReportCatalog[] }>('/design/reports'),
        ]);
        types.value = typesRes.data;
        formCatalog.value = formsRes.data;
        reportCatalog.value = reportsRes.data;

        if (canAssignWorkflow.value) {
            const wfRes = await api<{ data: WorkflowDef[] }>('/design/workflows');
            workflows.value = wfRes.data.filter((w) => w.status === 'published');
        } else {
            workflows.value = [];
        }
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function createType() {
    if (!newName.value.trim()) {
        toast.warning('Indica el nombre del tipo.');
        return;
    }
    creating.value = true;
    try {
        await api('/routine-types', {
            method: 'POST',
            body: JSON.stringify({
                name: newName.value.trim(),
                slug: newSlug.value.trim() || undefined,
                service_line: newServiceLine.value,
            }),
        });
        newName.value = '';
        newSlug.value = '';
        newServiceLine.value = 'maintenance';
        showCreate.value = false;
        toast.success('Tipo de rutina creado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        creating.value = false;
    }
}

async function saveServiceLine(type: RoutineType, serviceLine: string) {
    if (serviceLine === (type.service_line ?? 'maintenance')) {
        return;
    }
    try {
        await api(`/routine-types/${type.id}`, {
            method: 'PUT',
            body: JSON.stringify({ service_line: serviceLine }),
        });
        toast.success('Línea de servicio actualizada.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function renameType(type: RoutineType, name: string) {
    if (name.trim() === type.name) {
        return;
    }
    try {
        await api(`/routine-types/${type.id}`, {
            method: 'PUT',
            body: JSON.stringify({ name: name.trim() }),
        });
        toast.success('Nombre actualizado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function toggleActive(type: RoutineType) {
    try {
        await api(`/routine-types/${type.id}`, {
            method: 'PUT',
            body: JSON.stringify({ is_active: !type.is_active }),
        });
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function deleteType(type: RoutineType) {
    if (!window.confirm(`¿Eliminar el tipo «${type.name}»?`)) {
        return;
    }
    try {
        await api(`/routine-types/${type.id}`, { method: 'DELETE' });
        toast.success('Tipo eliminado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function saveWorkflow(type: RoutineType, workflowId: string) {
    try {
        await api(`/routine-types/${type.id}/workflow`, {
            method: 'PUT',
            body: JSON.stringify({
                workflow_definition_id: workflowId ? Number(workflowId) : null,
            }),
        });
        toast.success('Workflow actualizado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function saveFormVersion(type: RoutineType, formVersionId: string) {
    try {
        await api(`/routine-types/${type.id}/design`, {
            method: 'PUT',
            body: JSON.stringify({
                form_version_id: formVersionId ? Number(formVersionId) : null,
            }),
        });
        toast.success('Formulario enlazado al tipo de rutina.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function saveReportVersion(type: RoutineType, reportVersionId: string) {
    try {
        await api(`/routine-types/${type.id}/design`, {
            method: 'PUT',
            body: JSON.stringify({
                report_template_version_id: reportVersionId ? Number(reportVersionId) : null,
            }),
        });
        toast.success('Reporte enlazado al tipo de rutina.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

onMounted(load);
</script>

<template>
    <div class="portal-page" data-tour="page-routine-types">
        <SectionSubnav :items="routinesSectionNav" />
        <PageHeader
            title="Tipos de rutina"
            subtitle="Crea tipos y enlaza formulario e informe publicados (se valida la alineación de campos). Solo se pueden elegir versiones publicadas del informe."
        />
        <div v-if="canWrite" class="mb-4 flex flex-wrap items-end gap-3">
            <AppButton v-if="!showCreate" variant="secondary" @click="showCreate = true">
                Nuevo tipo
            </AppButton>
            <div v-else class="portal-form-panel flex flex-wrap items-end gap-3 p-4">
                <MaterialField v-model="newName" label="Nombre *" class="min-w-[14rem]" />
                <MaterialField
                    v-model="newSlug"
                    label="Slug (opcional)"
                    class="min-w-[12rem]"
                    placeholder="auto-desde-nombre"
                />
                <MaterialSelect
                    v-model="newServiceLine"
                    label="Línea de servicio *"
                    class="min-w-[12rem]"
                    :options="SERVICE_LINE_OPTIONS"
                />
                <AppButton :disabled="creating" @click="createType">Crear</AppButton>
                <AppButton variant="ghost" @click="showCreate = false">Cancelar</AppButton>
            </div>
        </div>
        <p v-if="loading" class="text-portal-muted">Cargando…</p>
        <ReadOnlyNotice v-if="!loading && !canWrite" module-label="Tipos de rutina" />
        <ConfigurableDataTable
            v-if="!loading"
            table-id="routine-types"
            :columns="routineTypeTableColumns"
            :rows="types"
            row-key="id"
        >
            <template #name="{ row }">
                <template v-if="canWrite">
                    <input
                        :value="(row as RoutineType).name"
                        class="w-full min-w-[10rem] rounded-md border border-white/15 bg-transparent px-2 py-1.5 text-sm text-portal-heading"
                        @change="(e) => renameType(row as RoutineType, (e.target as HTMLInputElement).value)"
                    />
                </template>
                <span v-else class="text-portal-heading font-medium">{{ (row as RoutineType).name }}</span>
                <p class="text-portal-muted mt-1 font-mono text-xs">{{ (row as RoutineType).slug }}</p>
            </template>
            <template #service_line="{ row }">
                <MaterialSelect
                    v-if="canWrite"
                    compact
                    :model-value="(row as RoutineType).service_line ?? 'maintenance'"
                    label="Línea"
                    :options="SERVICE_LINE_OPTIONS"
                    @update:model-value="(v) => saveServiceLine(row as RoutineType, String(v))"
                />
                <span v-else class="text-portal-muted text-sm">
                    {{ SERVICE_LINE_LABELS[(row as RoutineType).service_line ?? 'maintenance'] ?? 'Mantenimiento' }}
                </span>
            </template>
            <template #status="{ row }">
                <span
                    class="text-xs font-medium"
                    :class="(row as RoutineType).is_active ? 'text-emerald-600' : 'text-slate-500'"
                >
                    {{ (row as RoutineType).is_active ? 'Activo' : 'Inactivo' }}
                </span>
            </template>
            <template #form="{ row }">
                <MaterialSelect
                    compact
                    :disabled="!canLinkForm"
                    :model-value="String((row as RoutineType).form_version_id ?? '')"
                    label="Formulario"
                    :options="formSelectOptions()"
                    @update:model-value="(v) => saveFormVersion(row as RoutineType, String(v))"
                />
                <p v-if="!formOptions.length" class="mt-1 text-xs text-amber-600">Publica un formulario en D1.</p>
            </template>
            <template #report="{ row }">
                <MaterialSelect
                    compact
                    :disabled="!canLinkReport"
                    :model-value="String((row as RoutineType).report_template_version_id ?? '')"
                    label="Reporte"
                    :options="reportSelectOptions()"
                    @update:model-value="(v) => saveReportVersion(row as RoutineType, String(v))"
                />
                <p v-if="!reportOptions.length && !unpublishedReportNames.length" class="mt-1 text-xs text-amber-600">
                    Publica un reporte en Diseño → Reportes.
                </p>
                <p v-else-if="!reportOptions.length && unpublishedReportNames.length" class="mt-1 text-xs text-amber-600">
                    Tienes borradores sin publicar ({{ unpublishedReportNames.join(', ') }}). Abre cada plantilla en
                    Diseño → Reportes y pulsa <strong class="text-portal-heading">Publicar</strong> para poder enlazarla aquí.
                </p>
                <p
                    v-if="(row as RoutineType).field_alignment?.checked && !(row as RoutineType).field_alignment?.aligned"
                    class="portal-msg-warning mt-2 text-xs"
                >
                    Desalineado: {{ (row as RoutineType).field_alignment?.missing?.join(', ') }}
                </p>
                <p
                    v-else-if="(row as RoutineType).field_alignment?.checked && (row as RoutineType).field_alignment?.aligned"
                    class="portal-msg-success mt-2 text-xs"
                >
                    Formulario e informe alineados
                </p>
            </template>
            <template #workflow="{ row }">
                <MaterialSelect
                    v-if="canAssignWorkflow"
                    compact
                    :disabled="!canWrite"
                    :model-value="String((row as RoutineType).workflow_definition_id ?? '')"
                    label="Workflow"
                    :options="workflowSelectOptions()"
                    @update:model-value="(v) => saveWorkflow(row as RoutineType, String(v))"
                />
                <div v-else class="space-y-1">
                    <p class="text-portal-muted text-xs font-medium uppercase tracking-wide">Workflow</p>
                    <p class="text-portal-heading text-sm">
                        {{ (row as RoutineType).workflow_definition?.name ?? '— Sin asignar —' }}
                    </p>
                    <p class="text-portal-muted text-xs">Solo el administrador de plataforma puede cambiarlo.</p>
                </div>
            </template>
            <template #actions="{ row }">
                <div class="table-row-actions">
                    <IconActionButton
                        icon="power"
                        :label="(row as RoutineType).is_active ? 'Desactivar tipo de rutina' : 'Activar tipo de rutina'"
                        @click="toggleActive(row as RoutineType)"
                    />
                    <IconActionButton
                        icon="trash"
                        label="Eliminar tipo de rutina"
                        variant="danger"
                        @click="deleteType(row as RoutineType)"
                    />
                </div>
            </template>
        </ConfigurableDataTable>
    </div>
</template>
