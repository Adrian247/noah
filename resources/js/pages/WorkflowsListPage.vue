<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/api/client';

type Workflow = { id: number; name: string; slug: string; version: number; status: string };

const items = ref<Workflow[]>([]);
const loading = ref(true);

onMounted(async () => {
    const res = await api<{ data: Workflow[] }>('/design/workflows');
    items.value = res.data;
    loading.value = false;
});
</script>

<template>
    <div class="space-y-4">
        <h2 class="text-xl font-semibold">Workflows</h2>
        <p class="text-sm text-slate-600">Diseño visual del flujo lineal de validación de rutinas.</p>
        <p v-if="loading" class="text-slate-500">Cargando…</p>
        <ul v-else class="divide-y rounded-lg border bg-white">
            <li v-for="w in items" :key="w.id" class="flex justify-between px-4 py-3 text-sm">
                <RouterLink class="font-medium underline" :to="`/app/design/workflows/${w.id}`">
                    {{ w.name }}
                </RouterLink>
                <span class="text-xs text-slate-500">{{ w.slug }} · v{{ w.version }}</span>
            </li>
        </ul>
    </div>
</template>
