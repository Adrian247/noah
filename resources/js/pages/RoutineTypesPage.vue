<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '@/api/client';

type RoutineType = {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
    form_version?: { id: number; version: number } | null;
    report_template_version?: { id: number; version: number } | null;
};

const types = ref<RoutineType[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

onMounted(async () => {
    try {
        const res = await api<{ data: RoutineType[] }>('/routine-types');
        types.value = res.data;
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="space-y-4">
        <h2 class="text-xl font-semibold">Tipos de rutina</h2>
        <p class="text-sm text-slate-600">
            Configuración de formulario y reporte por tipo (lectura; diseñadores en fase posterior).
        </p>
        <p v-if="loading" class="text-slate-500">Cargando…</p>
        <p v-else-if="error" class="text-red-600">{{ error }}</p>
        <table v-else class="w-full text-left text-sm">
            <thead>
                <tr class="border-b text-slate-500">
                    <th class="py-2">Nombre</th>
                    <th>Slug</th>
                    <th>Form v</th>
                    <th>Reporte v</th>
                    <th>Activo</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="t in types"
                    :key="t.id"
                    class="border-b border-slate-100"
                >
                    <td class="py-2 font-medium">{{ t.name }}</td>
                    <td class="font-mono text-xs">{{ t.slug }}</td>
                    <td>{{ t.form_version?.version ?? '—' }}</td>
                    <td>{{ t.report_template_version?.version ?? '—' }}</td>
                    <td>{{ t.is_active ? 'Sí' : 'No' }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
