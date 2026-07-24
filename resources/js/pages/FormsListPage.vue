<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/api/client';

type FormRow = {
    id: number;
    name: string;
    slug: string;
    latest_version?: { version: number; status: string } | null;
};

const forms = ref<FormRow[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const name = ref('');

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: FormRow[] }>('/design/forms');
        forms.value = res.data;
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
}

async function createForm() {
    if (!name.value.trim()) {
        return;
    }
    error.value = null;
    try {
        await api('/design/forms', {
            method: 'POST',
            body: JSON.stringify({ name: name.value.trim() }),
        });
        name.value = '';
        await load();
    } catch (e) {
        error.value = (e as Error).message;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <h2 class="text-xl font-semibold">Formularios</h2>
        <p class="text-sm text-slate-600">Solo administradores pueden crear y publicar versiones.</p>
        <form class="flex flex-wrap gap-2" @submit.prevent="createForm">
            <input
                v-model="name"
                placeholder="Nombre del formulario"
                class="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
            />
            <button type="submit" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm text-white">
                Crear
            </button>
        </form>
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
        <p v-if="loading" class="text-slate-500">Cargando…</p>
        <ul v-else class="divide-y rounded-lg border bg-white">
            <li v-for="f in forms" :key="f.id" class="flex items-center justify-between px-4 py-3 text-sm">
                <div>
                    <RouterLink class="font-medium underline" :to="`/app/design/forms/${f.id}`">
                        {{ f.name }}
                    </RouterLink>
                    <p class="text-xs text-slate-500">{{ f.slug }}</p>
                </div>
                <span class="text-xs text-slate-500">
                    v{{ f.latest_version?.version ?? '—' }} · {{ f.latest_version?.status ?? '—' }}
                </span>
            </li>
        </ul>
    </div>
</template>
