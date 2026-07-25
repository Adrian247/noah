<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';

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
const canWrite = computed(() => canWriteModule('design_routine_types'));

const types = ref<RoutineType[]>([]);
const workflows = ref<WorkflowDef[]>([]);
const formCatalog = ref<FormCatalog[]>([]);
const reportCatalog = ref<ReportCatalog[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const message = ref<string | null>(null);

const formOptions = computed(() =>
    formCatalog.value
        .filter((f) => f.published_version)
        .map((f) => ({
            id: f.published_version!.id,
            label: `${f.name} · v${f.published_version!.version}`,
        })),
);

const reportOptions = computed(() =>
    reportCatalog.value
        .filter((r) => r.published_version)
        .map((r) => ({
            id: r.published_version!.id,
            label: `${r.name} · v${r.published_version!.version}`,
        })),
);

async function load() {
    loading.value = true;
    try {
        const [typesRes, wfRes, formsRes, reportsRes] = await Promise.all([
            api<{ data: RoutineType[] }>('/routine-types'),
            api<{ data: WorkflowDef[] }>('/design/workflows'),
            api<{ data: FormCatalog[] }>('/design/forms'),
            api<{ data: ReportCatalog[] }>('/design/reports'),
        ]);
        types.value = typesRes.data;
        workflows.value = wfRes.data;
        formCatalog.value = formsRes.data;
        reportCatalog.value = reportsRes.data;
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
}

async function saveWorkflow(type: RoutineType, workflowId: string) {
    message.value = null;
    try {
        await api(`/routine-types/${type.id}/workflow`, {
            method: 'PUT',
            body: JSON.stringify({
                workflow_definition_id: workflowId ? Number(workflowId) : null,
            }),
        });
        message.value = 'Workflow actualizado.';
        await load();
    } catch (e) {
        message.value = (e as Error).message;
    }
}

async function saveFormVersion(type: RoutineType, formVersionId: string) {
    message.value = null;
    try {
        await api(`/routine-types/${type.id}/design`, {
            method: 'PUT',
            body: JSON.stringify({
                form_version_id: formVersionId ? Number(formVersionId) : null,
            }),
        });
        message.value = 'Formulario enlazado al tipo de rutina.';
        await load();
    } catch (e) {
        message.value = (e as Error).message;
    }
}

async function saveReportVersion(type: RoutineType, reportVersionId: string) {
    message.value = null;
    try {
        await api(`/routine-types/${type.id}/design`, {
            method: 'PUT',
            body: JSON.stringify({
                report_template_version_id: reportVersionId ? Number(reportVersionId) : null,
            }),
        });
        message.value = 'Reporte enlazado al tipo de rutina.';
        await load();
    } catch (e) {
        message.value = (e as Error).message;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <h2 class="text-xl font-semibold">Tipos de rutina</h2>
        <p class="text-sm text-slate-600">
            Aquí se define qué <strong>versión publicada</strong> de formulario y reporte usa cada tipo. Primero
            publica en Diseño → Formularios / Reportes (D1 y D2), luego elige la versión en los desplegables.
        </p>
        <p v-if="loading" class="text-slate-500">Cargando…</p>
        <p v-else-if="error" class="text-red-600">{{ error }}</p>
        <ReadOnlyNotice v-if="!loading && !error && !canWrite" module-label="Tipos de rutina" />
        <table v-if="!loading && !error" class="w-full text-left text-sm">
            <thead>
                <tr class="border-b text-slate-500">
                    <th class="py-2">Nombre</th>
                    <th>Formulario (publicado)</th>
                    <th>Reporte (publicado)</th>
                    <th>Workflow</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="t in types" :key="t.id" class="border-b border-slate-100 align-top">
                    <td class="py-2 font-medium">{{ t.name }}</td>
                    <td class="py-2">
                        <select
                            class="max-w-xs w-full rounded border border-slate-300 px-2 py-1 text-xs"
                            :disabled="!canWrite"
                            :value="String(t.form_version_id ?? '')"
                            @change="saveFormVersion(t, ($event.target as HTMLSelectElement).value)"
                        >
                            <option value="">— Sin enlazar —</option>
                            <option v-for="o in formOptions" :key="o.id" :value="String(o.id)">
                                {{ o.label }}
                            </option>
                        </select>
                        <p v-if="!formOptions.length" class="mt-1 text-xs text-amber-800">
                            Publica un formulario en D1.
                        </p>
                    </td>
                    <td class="py-2">
                        <select
                            class="max-w-xs w-full rounded border border-slate-300 px-2 py-1 text-xs"
                            :disabled="!canWrite"
                            :value="String(t.report_template_version_id ?? '')"
                            @change="saveReportVersion(t, ($event.target as HTMLSelectElement).value)"
                        >
                            <option value="">— Sin enlazar —</option>
                            <option v-for="o in reportOptions" :key="o.id" :value="String(o.id)">
                                {{ o.label }}
                            </option>
                        </select>
                        <p v-if="!reportOptions.length" class="mt-1 text-xs text-amber-800">
                            Publica un reporte en D2.
                        </p>
                    </td>
                    <td class="py-2">
                        <select
                            class="rounded border border-slate-300 px-2 py-1 text-xs"
                            :disabled="!canWrite"
                            :value="String(t.workflow_definition_id ?? '')"
                            @change="saveWorkflow(t, ($event.target as HTMLSelectElement).value)"
                        >
                            <option value="">—</option>
                            <option v-for="w in workflows" :key="w.id" :value="String(w.id)">
                                {{ w.name }}
                            </option>
                        </select>
                    </td>
                </tr>
            </tbody>
        </table>
        <p v-if="message" class="text-sm text-slate-600">{{ message }}</p>
    </div>
</template>
