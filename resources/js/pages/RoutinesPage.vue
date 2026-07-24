<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/api/client';

type Routine = {
    id: number;
    status: string;
    asset?: { tag: string };
    routine_type?: { name: string };
};

const routines = ref<Routine[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

onMounted(async () => {
    try {
        const res = await api<{ data: Routine[] }>('/routines');
        routines.value = res.data;
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div>
        <h2 class="text-xl font-semibold">Rutinas</h2>
        <p v-if="loading" class="mt-4 text-slate-500">Cargando…</p>
        <p v-else-if="error" class="mt-4 text-red-600">{{ error }}</p>
        <p v-else-if="routines.length === 0" class="mt-4 text-slate-500">Sin rutinas.</p>
        <table v-else class="mt-4 w-full text-left text-sm">
            <thead>
                <tr class="border-b text-slate-500">
                    <th class="py-2">ID</th>
                    <th>Tipo</th>
                    <th>Activo</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="r in routines"
                    :key="r.id"
                    class="border-b border-slate-100 hover:bg-slate-50"
                >
                    <td class="py-2">
                        <RouterLink class="text-slate-900 underline" :to="`/app/routines/${r.id}`">
                            {{ r.id }}
                        </RouterLink>
                    </td>
                    <td>{{ r.routine_type?.name ?? '—' }}</td>
                    <td>{{ r.asset?.tag ?? '—' }}</td>
                    <td>{{ r.status }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
