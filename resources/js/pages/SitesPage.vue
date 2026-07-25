<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';

type Site = { id: number; name: string; address?: string | null };

const { canWriteModule } = useModuleAccess();
const canWrite = computed(() => canWriteModule('sites'));

const sites = ref<Site[]>([]);
const loading = ref(true);
const message = ref<string | null>(null);
const form = ref({ name: '', address: '' });
const editingId = ref<number | null>(null);

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: Site[] }>('/sites');
        sites.value = res.data;
    } catch (e) {
        message.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
}

async function save() {
    message.value = null;
    try {
        if (editingId.value) {
            await api(`/sites/${editingId.value}`, {
                method: 'PUT',
                body: JSON.stringify({
                    name: form.value.name,
                    address: form.value.address || null,
                }),
            });
        } else {
            await api('/sites', {
                method: 'POST',
                body: JSON.stringify({
                    name: form.value.name,
                    address: form.value.address || null,
                }),
            });
        }
        form.value = { name: '', address: '' };
        editingId.value = null;
        await load();
    } catch (e) {
        message.value = (e as Error).message;
    }
}

function edit(site: Site) {
    editingId.value = site.id;
    form.value = { name: site.name, address: site.address ?? '' };
}

async function remove(id: number) {
    if (!window.confirm('¿Eliminar sitio?')) {
        return;
    }
    try {
        await api(`/sites/${id}`, { method: 'DELETE' });
        await load();
    } catch (e) {
        message.value = (e as Error).message;
    }
}

onMounted(load);
</script>

<template>
    <div class="max-w-3xl space-y-4">
        <h2 class="text-xl font-semibold">Sitios</h2>
        <p v-if="loading" class="text-slate-500">Cargando…</p>
        <table v-else class="w-full text-left text-sm">
            <thead>
                <tr class="border-b text-slate-500">
                    <th class="py-2">Nombre</th>
                    <th>Dirección</th>
                    <th v-if="canWrite" />
                </tr>
            </thead>
            <tbody>
                <tr v-for="s in sites" :key="s.id" class="border-b border-slate-100">
                    <td class="py-2">{{ s.name }}</td>
                    <td>{{ s.address ?? '—' }}</td>
                    <td v-if="canWrite" class="space-x-2 text-right">
                        <button type="button" class="text-sm underline" @click="edit(s)">Editar</button>
                        <button type="button" class="text-sm text-red-700" @click="remove(s.id)">Borrar</button>
                    </td>
                </tr>
            </tbody>
        </table>
        <form v-if="canWrite" class="rounded-lg border bg-white p-4 space-y-3 text-sm" @submit.prevent="save">
            <p class="font-medium">{{ editingId ? 'Editar sitio' : 'Nuevo sitio' }}</p>
            <label class="block">
                Nombre
                <input v-model="form.name" required class="mt-1 w-full rounded-md border px-2 py-1.5" />
            </label>
            <label class="block">
                Dirección
                <input v-model="form.address" class="mt-1 w-full rounded-md border px-2 py-1.5" />
            </label>
            <button type="submit" class="rounded-md bg-slate-900 px-3 py-2 text-white">Guardar</button>
        </form>
        <ReadOnlyNotice v-else module-label="Sitios" />
        <p v-if="message" class="text-sm text-red-600">{{ message }}</p>
    </div>
</template>
