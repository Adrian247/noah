<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '@/api/client';

type WorkflowDef = { id: number; name: string };
type RoutineType = {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
    workflow_definition_id?: number | null;
    form_version?: { id: number; version: number } | null;
    report_template_version?: { id: number; version: number } | null;
    workflow_definition?: WorkflowDef | null;
};

const types = ref<RoutineType[]>([]);
const workflows = ref<WorkflowDef[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const message = ref<string | null>(null);

async function load() {
    loading.value = true;
    try {
        const [typesRes, wfRes] = await Promise.all([
            api<{ data: RoutineType[] }>('/routine-types'),
            api<{ data: WorkflowDef[] }>('/design/workflows'),
        ]);
        types.value = typesRes.data;
        workflows.value = wfRes.data;
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

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <h2 class="text-xl font-semibold">Tipos de rutina</h2>
        <p class="text-sm text-slate-600">Enlaza formulario, reporte y workflow por tipo (workflow: admin).</p>
        <p v-if="loading" class="text-slate-500">Cargando…</p>
        <p v-else-if="error" class="text-red-600">{{ error }}</p>
        <table v-else class="w-full text-left text-sm">
            <thead>
                <tr class="border-b text-slate-500">
                    <th class="py-2">Nombre</th>
                    <th>Form v</th>
                    <th>Reporte v</th>
                    <th>Workflow</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="t in types" :key="t.id" class="border-b border-slate-100">
                    <td class="py-2 font-medium">{{ t.name }}</td>
                    <td>{{ t.form_version?.version ?? '—' }}</td>
                    <td>{{ t.report_template_version?.version ?? '—' }}</td>
                    <td>
                        <select
                            class="rounded border border-slate-300 px-2 py-1 text-xs"
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
