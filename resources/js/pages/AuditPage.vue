<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';

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

const toast = useToast();
const entries = ref<Entry[]>([]);
const loading = ref(true);

onMounted(async () => {
    try {
        const res = await api<{ data: Entry[] }>('/audit/entries?per_page=50');
        entries.value = res.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="portal-page">
        <PageHeader
            title="Auditoría"
            subtitle="Registro append-only de acciones recientes en la empresa activa."
        />
        <p v-if="loading" class="text-portal-muted">Cargando…</p>
        <p v-else-if="entries.length === 0" class="text-portal-muted text-sm">Sin registros recientes.</p>
        <div v-else class="portal-table-wrap">
            <table class="portal-data-table">
                <thead>
                    <tr class="border-b">
                        <th class="py-2">Fecha</th>
                        <th>Acción</th>
                        <th>Actor</th>
                        <th>Referencia</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="entry in entries" :key="entry.id" class="border-b align-top">
                        <td class="text-portal-muted py-2 text-xs whitespace-nowrap">
                            {{ new Date(entry.occurred_at).toLocaleString() }}
                        </td>
                        <td class="text-portal-heading font-mono text-xs">{{ entry.action }}</td>
                        <td class="text-portal-heading">{{ entry.actor?.name ?? '—' }}</td>
                        <td class="text-portal-muted text-xs">
                            <span v-if="entry.subject_type">{{ entry.subject_type }} #{{ entry.subject_id }}</span>
                            <span v-else>—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
