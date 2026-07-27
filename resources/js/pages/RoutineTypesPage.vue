<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useToast } from '@/composables/useToast';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import AppButton from '@/components/ui/AppButton.vue';

type WorkflowDef = { id: number; name: string };
type VersionOption = { id: number; version: number };
type FormCatalog = { id: number; name: string; published_version?: VersionOption | null };
type ReportCatalog = { id: number; name: string; published_version?: VersionOption | null };

type RoutineType = {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
    workflow_definition_id?: number | null;
    form_version_id?: number | null;
    report_template_version_id?: number | null;
    form_version?: { id: number; version: number; definition?: { name: string } } | null;
    report_template_version?: { id: number; version: number; template?: { name: string } } | null;
    workflow_definition?: WorkflowDef | null;
};

const { canWriteModule } = useModuleAccess();
const toast = useToast();
const canWrite = computed(() => canWriteModule('design_routine_types'));

const types = ref<RoutineType[]>([]);
const workflows = ref<WorkflowDef[]>([]);
const formCatalog = ref<FormCatalog[]>([]);
const reportCatalog = ref<ReportCatalog[]>([]);
const loading = ref(true);

const showCreate = ref(false);
const newName = ref('');
const newSlug = ref('');
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

const workflowOptions = computed(() =>
    workflows.value.map((w) => ({ value: String(w.id), label: w.name })),
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
        const [typesRes, wfRes, formsRes, reportsRes] = await Promise.all([
            api<{ data: RoutineType[] }>('/routine-types?all=1'),
            api<{ data: WorkflowDef[] }>('/design/workflows'),
            api<{ data: FormCatalog[] }>('/design/forms'),
            api<{ data: ReportCatalog[] }>('/design/reports'),
        ]);
        types.value = typesRes.data;
        workflows.value = wfRes.data;
        formCatalog.value = formsRes.data;
        reportCatalog.value = reportsRes.data;
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
            }),
        });
        newName.value = '';
        newSlug.value = '';
        showCreate.value = false;
        toast.success('Tipo de rutina creado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        creating.value = false;
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
    <div class="portal-page">
        <PageHeader
            title="Tipos de rutina"
            subtitle="Crea tipos y define qué versión publicada de formulario y reporte usa cada uno."
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
                <AppButton :disabled="creating" @click="createType">Crear</AppButton>
                <AppButton variant="ghost" @click="showCreate = false">Cancelar</AppButton>
            </div>
        </div>
        <p v-if="loading" class="text-portal-muted">Cargando…</p>
        <ReadOnlyNotice v-if="!loading && !canWrite" module-label="Tipos de rutina" />
        <div v-if="!loading" class="portal-table-wrap">
        <table class="portal-data-table">
            <thead>
                <tr class="border-b">
                    <th class="py-2">Nombre</th>
                    <th>Estado</th>
                    <th>Formulario (publicado)</th>
                    <th>Reporte (publicado)</th>
                    <th>Workflow</th>
                    <th v-if="canWrite" class="py-2">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="t in types" :key="t.id" class="border-b align-top">
                    <td class="py-3 pr-2">
                        <input
                            v-if="canWrite"
                            :value="t.name"
                            class="w-full min-w-[10rem] rounded-md border border-white/15 bg-transparent px-2 py-1.5 text-sm text-portal-heading"
                            @change="(e) => renameType(t, (e.target as HTMLInputElement).value)"
                        />
                        <span v-else class="text-portal-heading font-medium">{{ t.name }}</span>
                        <p class="text-portal-muted mt-1 font-mono text-xs">{{ t.slug }}</p>
                    </td>
                    <td class="py-3">
                        <span
                            class="text-xs font-medium"
                            :class="t.is_active ? 'text-emerald-600' : 'text-slate-500'"
                        >
                            {{ t.is_active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td class="max-w-xs py-3 pr-2">
                        <MaterialSelect
                            compact
                            :disabled="!canWrite"
                            :model-value="String(t.form_version_id ?? '')"
                            label="Formulario"
                            :options="formSelectOptions()"
                            @update:model-value="(v) => saveFormVersion(t, String(v))"
                        />
                        <p v-if="!formOptions.length" class="mt-1 text-xs text-amber-600">
                            Publica un formulario en D1.
                        </p>
                    </td>
                    <td class="max-w-xs py-3 pr-2">
                        <MaterialSelect
                            compact
                            :disabled="!canWrite"
                            :model-value="String(t.report_template_version_id ?? '')"
                            label="Reporte"
                            :options="reportSelectOptions()"
                            @update:model-value="(v) => saveReportVersion(t, String(v))"
                        />
                        <p v-if="!reportOptions.length" class="mt-1 text-xs text-amber-600">
                            Publica un reporte en D2.
                        </p>
                    </td>
                    <td class="max-w-[10rem] py-3">
                        <MaterialSelect
                            compact
                            :disabled="!canWrite"
                            :model-value="String(t.workflow_definition_id ?? '')"
                            label="Workflow"
                            :options="workflowSelectOptions()"
                            @update:model-value="(v) => saveWorkflow(t, String(v))"
                        />
                    </td>
                    <td v-if="canWrite" class="space-y-2 py-3">
                        <button
                            type="button"
                            class="block text-sm text-amber-700 underline"
                            @click="toggleActive(t)"
                        >
                            {{ t.is_active ? 'Desactivar' : 'Activar' }}
                        </button>
                        <button
                            type="button"
                            class="block text-sm text-red-500 underline"
                            @click="deleteType(t)"
                        >
                            Eliminar
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
        </div>
    </div>
</template>
