<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/api/client';
import PageHeader from '@/components/ui/PageHeader.vue';

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
    <div class="portal-page">
        <PageHeader
            title="Workflows"
            subtitle="Diseño visual del flujo lineal de validación de rutinas."
        />
        <p v-if="loading" class="text-portal-muted">Cargando…</p>
        <ul v-else class="portal-list-panel divide-y">
            <li v-for="w in items" :key="w.id" class="flex justify-between px-4 py-3 text-sm">
                <RouterLink class="text-portal-link font-medium underline" :to="`/app/design/workflows/${w.id}`">
                    {{ w.name }}
                </RouterLink>
                <span class="text-portal-muted text-xs">{{ w.slug }} · v{{ w.version }}</span>
            </li>
        </ul>
    </div>
</template>
