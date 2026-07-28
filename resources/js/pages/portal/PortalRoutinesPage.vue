<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import StatusBadge from '@/components/ui/StatusBadge.vue';

type RoutineRow = {
    id: number;
    status: string;
    asset?: { tag: string; serial_number?: string };
    routine_type?: { name: string };
    invoice?: { id: number; status: string } | null;
};

const toast = useToast();
const items = ref<RoutineRow[]>([]);
const loading = ref(true);

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: RoutineRow[] } | { data: { data: RoutineRow[] } }>('/portal/routines');
        const payload = res as { data?: RoutineRow[] | { data: RoutineRow[] } };
        if (Array.isArray(payload.data)) {
            items.value = payload.data;
        } else if (payload.data && Array.isArray((payload.data as { data: RoutineRow[] }).data)) {
            items.value = (payload.data as { data: RoutineRow[] }).data;
        }
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <h2 class="text-xl font-semibold">Rutinas de mis equipos</h2>
        <p class="text-sm text-slate-600">Historial según equipos vinculados a tu cuenta por número de serie.</p>
        <p v-if="loading" class="text-slate-500">Cargando…</p>
        <ul v-else class="divide-y rounded-xl border border-slate-200 bg-white">
            <li v-for="r in items" :key="r.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                <div>
                    <RouterLink class="font-medium text-amber-800 underline" :to="`/portal/routines/${r.id}`">
                        Rutina #{{ r.id }} — {{ r.routine_type?.name }}
                    </RouterLink>
                    <p class="text-slate-500">Activo {{ r.asset?.tag }} · Serie {{ r.asset?.serial_number }}</p>
                </div>
                <StatusBadge :status="r.status" />
            </li>
            <li v-if="!items.length" class="px-4 py-6 text-center text-slate-500">Sin rutinas visibles.</li>
        </ul>
    </div>
</template>
