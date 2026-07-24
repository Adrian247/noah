<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '@/api/client';

type CatalogItem = {
    id: number;
    code: string;
    name: string;
    manufacturer?: string | null;
};

const items = ref<CatalogItem[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const saving = ref(false);

const form = ref({
    code: '',
    name: '',
    manufacturer: '',
});

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const res = await api<{ data: CatalogItem[] }>('/catalog/items');
        items.value = res.data;
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
}

async function submit() {
    saving.value = true;
    error.value = null;
    try {
        await api('/catalog/items', {
            method: 'POST',
            body: JSON.stringify({
                code: form.value.code,
                name: form.value.name,
                manufacturer: form.value.manufacturer || null,
            }),
        });
        form.value = { code: '', name: '', manufacturer: '' };
        await load();
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-6">
        <h2 class="text-xl font-semibold">Catálogo de equipos</h2>
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

        <form
            class="max-w-lg space-y-3 rounded-lg border border-slate-200 bg-white p-4"
            @submit.prevent="submit"
        >
            <p class="text-sm font-medium text-slate-700">Nuevo equipo de catálogo</p>
            <label class="block text-sm">
                Código
                <input
                    v-model="form.code"
                    required
                    class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5"
                />
            </label>
            <label class="block text-sm">
                Nombre
                <input
                    v-model="form.name"
                    required
                    class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5"
                />
            </label>
            <label class="block text-sm">
                Fabricante
                <input
                    v-model="form.manufacturer"
                    class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5"
                />
            </label>
            <button
                type="submit"
                class="rounded-md bg-slate-900 px-3 py-2 text-sm text-white disabled:opacity-50"
                :disabled="saving"
            >
                Guardar
            </button>
        </form>

        <p v-if="loading" class="text-slate-500">Cargando…</p>
        <table v-else class="w-full text-left text-sm">
            <thead>
                <tr class="border-b text-slate-500">
                    <th class="py-2">Código</th>
                    <th>Nombre</th>
                    <th>Fabricante</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="item in items"
                    :key="item.id"
                    class="border-b border-slate-100"
                >
                    <td class="py-2 font-mono text-xs">{{ item.code }}</td>
                    <td>{{ item.name }}</td>
                    <td>{{ item.manufacturer ?? '—' }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
