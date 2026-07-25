<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';

type SupplyItem = {
    id: number;
    sku: string;
    name: string;
    unit?: string | null;
    standard_cost?: string | number | null;
};

const { canWriteModule } = useModuleAccess();
const canWrite = computed(() => canWriteModule('catalog_supplies'));

const items = ref<SupplyItem[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const saving = ref(false);

const form = ref({
    sku: '',
    name: '',
    unit: 'pza',
    standard_cost: '',
});

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const res = await api<{ data: SupplyItem[] }>('/inventory/supplies');
        items.value = res.data;
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
}

const editingId = ref<number | null>(null);
const editForm = ref({ sku: '', name: '', unit: '', standard_cost: '' });

function startEdit(item: SupplyItem) {
    editingId.value = item.id;
    editForm.value = {
        sku: item.sku,
        name: item.name,
        unit: item.unit ?? '',
        standard_cost: item.standard_cost != null ? String(item.standard_cost) : '',
    };
}

async function saveEdit(id: number) {
    await api(`/inventory/supplies/${id}`, {
        method: 'PUT',
        body: JSON.stringify({
            sku: editForm.value.sku,
            name: editForm.value.name,
            unit: editForm.value.unit || null,
            standard_cost: editForm.value.standard_cost ? Number(editForm.value.standard_cost) : null,
        }),
    });
    editingId.value = null;
    await load();
}

async function remove(id: number) {
    if (!window.confirm('¿Eliminar insumo?')) return;
    await api(`/inventory/supplies/${id}`, { method: 'DELETE' });
    await load();
}

async function submit() {
    saving.value = true;
    error.value = null;
    try {
        await api('/inventory/supplies', {
            method: 'POST',
            body: JSON.stringify({
                sku: form.value.sku,
                name: form.value.name,
                unit: form.value.unit || null,
                standard_cost: form.value.standard_cost
                    ? Number(form.value.standard_cost)
                    : null,
            }),
        });
        form.value = { sku: '', name: '', unit: 'pza', standard_cost: '' };
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
        <h2 class="text-xl font-semibold">Insumos</h2>
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

        <form
            v-if="canWrite"
            class="max-w-lg space-y-3 rounded-lg border border-slate-200 bg-white p-4"
            @submit.prevent="submit"
        >
            <p class="text-sm font-medium text-slate-700">Nuevo insumo</p>
            <label class="block text-sm">
                SKU
                <input
                    v-model="form.sku"
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
                Unidad
                <input
                    v-model="form.unit"
                    class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5"
                />
            </label>
            <label class="block text-sm">
                Costo estándar
                <input
                    v-model="form.standard_cost"
                    type="number"
                    min="0"
                    step="0.01"
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
        <p v-else class="text-sm text-slate-500">Solo lectura: no puedes crear ni editar insumos.</p>

        <p v-if="loading" class="text-slate-500">Cargando…</p>
        <table v-else class="w-full text-left text-sm">
            <thead>
                <tr class="border-b text-slate-500">
                    <th class="py-2">SKU</th>
                    <th>Nombre</th>
                    <th>Unidad</th>
                    <th>Costo</th>
                    <th v-if="canWrite"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in items" :key="item.id" class="border-b border-slate-100">
                    <template v-if="editingId === item.id">
                        <td><input v-model="editForm.sku" class="w-full rounded border px-1 text-xs" /></td>
                        <td><input v-model="editForm.name" class="w-full rounded border px-1" /></td>
                        <td><input v-model="editForm.unit" class="w-full rounded border px-1" /></td>
                        <td><input v-model="editForm.standard_cost" class="w-full rounded border px-1" /></td>
                        <td class="text-xs space-x-1">
                            <button type="button" class="underline" @click="saveEdit(item.id)">OK</button>
                            <button type="button" @click="editingId = null">×</button>
                        </td>
                    </template>
                    <template v-else>
                        <td class="py-2 font-mono text-xs">{{ item.sku }}</td>
                        <td>{{ item.name }}</td>
                        <td>{{ item.unit ?? '—' }}</td>
                        <td>{{ item.standard_cost ?? '—' }}</td>
                        <td v-if="canWrite" class="text-xs space-x-2">
                            <button type="button" class="underline" @click="startEdit(item)">Editar</button>
                            <button type="button" class="text-red-700" @click="remove(item.id)">Borrar</button>
                        </td>
                    </template>
                </tr>
            </tbody>
        </table>
    </div>
</template>
