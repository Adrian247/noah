<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '@/api/client';

type Actor = { id: number; name: string; email: string };
type Entry = {
    id: number;
    action: string;
    subject_type?: string | null;
    subject_id?: number | null;
    metadata?: Record<string, unknown> | null;
    occurred_at: string;
    actor?: Actor | null;
};

const entries = ref<Entry[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

onMounted(async () => {
    try {
        const res = await api<{ data: Entry[] }>('/audit/entries?per_page=50');
        entries.value = res.data;
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="space-y-4">
        <h2 class="text-xl font-semibold">Auditoría</h2>
        <p class="text-sm text-slate-600">Registro append-only de acciones recientes en la empresa activa.</p>
        <p v-if="loading" class="text-slate-500">Cargando…</p>
        <p v-else-if="error" class="text-red-600">{{ error }}</p>
        <table v-else class="w-full text-left text-sm">
            <thead>
                <tr class="border-b text-slate-500">
                    <th class="py-2">Fecha</th>
                    <th>Acción</th>
                    <th>Actor</th>
                    <th>Referencia</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="entry in entries"
                    :key="entry.id"
                    class="border-b border-slate-100 align-top"
                >
                    <td class="py-2 whitespace-nowrap text-xs text-slate-500">
                        {{ new Date(entry.occurred_at).toLocaleString() }}
                    </td>
                    <td class="font-mono text-xs">{{ entry.action }}</td>
                    <td>{{ entry.actor?.name ?? '—' }}</td>
                    <td class="text-xs text-slate-600">
                        <span v-if="entry.subject_type">{{ entry.subject_type }} #{{ entry.subject_id }}</span>
                        <span v-else>—</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
