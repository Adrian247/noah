<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppModal from '@/components/ui/AppModal.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import AppButton from '@/components/ui/AppButton.vue';

type SupplyItem = {
    id: number;
    sku: string;
    name: string;
    unit?: string | null;
    standard_cost?: string | number | null;
};

const { canWriteModule } = useModuleAccess();
const toast = useToast();
const canWrite = computed(() => canWriteModule('catalog_supplies'));

const items = ref<SupplyItem[]>([]);
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const editingId = ref<number | null>(null);

const form = ref({
    sku: '',
    name: '',
    unit: 'pza',
    standard_cost: '',
});

function resetForm() {
    form.value = { sku: '', name: '', unit: 'pza', standard_cost: '' };
    editingId.value = null;
}

function openCreate() {
    resetForm();
    showForm.value = true;
}

function openEdit(item: SupplyItem) {
    editingId.value = item.id;
    form.value = {
        sku: item.sku,
        name: item.name,
        unit: item.unit ?? '',
        standard_cost: item.standard_cost != null ? String(item.standard_cost) : '',
    };
    showForm.value = true;
}

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: SupplyItem[] }>('/inventory/supplies');
        items.value = res.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    const body = {
        sku: form.value.sku,
        name: form.value.name,
        unit: form.value.unit || null,
        standard_cost: form.value.standard_cost ? Number(form.value.standard_cost) : null,
    };
    try {
        if (editingId.value) {
            await api(`/inventory/supplies/${editingId.value}`, { method: 'PUT', body: JSON.stringify(body) });
        } else {
            await api('/inventory/supplies', { method: 'POST', body: JSON.stringify(body) });
        }
        const wasEdit = Boolean(editingId.value);
        showForm.value = false;
        resetForm();
        toast.success(wasEdit ? 'Insumo actualizado.' : 'Insumo creado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        saving.value = false;
    }
}

async function remove(id: number) {
    if (!window.confirm('¿Eliminar insumo?')) {
        return;
    }
    await api(`/inventory/supplies/${id}`, { method: 'DELETE' });
    toast.success('Insumo eliminado.');
    await load();
}

onMounted(load);
</script>

<template>
    <div class="portal-page">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <PageHeader class="flex-1" title="Insumos" subtitle="Catálogo de materiales consumibles en rutinas." />
            <AppButton v-if="canWrite" type="button" class="shrink-0" @click="openCreate">
                Nuevo insumo
            </AppButton>
        </div>
        <p v-if="!canWrite" class="text-portal-muted text-sm">Solo lectura: no puedes crear ni editar insumos.</p>

        <p v-if="loading" class="text-portal-muted">Cargando…</p>
        <div v-else class="portal-table-wrap">
            <table class="portal-data-table">
                <thead>
                    <tr class="border-b">
                        <th class="py-2">SKU</th>
                        <th>Nombre</th>
                        <th>Unidad</th>
                        <th>Costo</th>
                        <th v-if="canWrite" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in items" :key="item.id" class="border-b">
                        <td class="text-portal-heading py-2 font-mono text-xs">{{ item.sku }}</td>
                        <td class="text-portal-heading">{{ item.name }}</td>
                        <td class="text-portal-muted">{{ item.unit ?? '—' }}</td>
                        <td class="text-portal-muted">{{ item.standard_cost ?? '—' }}</td>
                        <td v-if="canWrite" class="space-x-2 text-xs">
                            <button type="button" class="text-portal-link underline" @click="openEdit(item)">
                                Editar
                            </button>
                            <button type="button" class="text-red-400" @click="remove(item.id)">Borrar</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AppModal
            :open="showForm && canWrite"
            :title="editingId ? 'Editar insumo' : 'Nuevo insumo'"
            size="sm"
            @close="showForm = false"
        >
            <form id="supply-form" class="space-y-4" @submit.prevent="save">
                <MaterialField v-model="form.sku" label="SKU" required />
                <MaterialField v-model="form.name" label="Nombre" required />
                <MaterialField v-model="form.unit" label="Unidad" />
                <MaterialField v-model="form.standard_cost" label="Costo estándar" type="number" />
            </form>
            <template #footer>
                <button
                    type="button"
                    class="text-portal-muted rounded-xl px-4 py-2 text-sm hover:bg-white/5"
                    @click="showForm = false"
                >
                    Cancelar
                </button>
                <AppButton type="submit" form="supply-form" :disabled="saving">Guardar</AppButton>
            </template>
        </AppModal>
    </div>
</template>
