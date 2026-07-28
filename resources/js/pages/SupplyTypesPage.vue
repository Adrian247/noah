<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useToast } from '@/composables/useToast';
import { api } from '@/api/client';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import SectionSubnav from '@/components/ui/SectionSubnav.vue';
import AppModal from '@/components/ui/AppModal.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import AppButton from '@/components/ui/AppButton.vue';
import { catalogSuppliesSectionNav } from '@/lib/sectionNav';

type SupplyType = {
    id: number;
    code: string;
    name: string;
    description?: string | null;
    sort_order?: number;
};

const { canWriteModule } = useModuleAccess();
const toast = useToast();
const canWrite = computed(() => canWriteModule('catalog_supplies'));

const items = ref<SupplyType[]>([]);
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const editingId = ref<number | null>(null);

const form = ref({
    code: '',
    name: '',
    description: '',
    sort_order: '0',
});

function resetForm() {
    form.value = { code: '', name: '', description: '', sort_order: '0' };
    editingId.value = null;
}

function openCreate() {
    resetForm();
    showForm.value = true;
}

function openEdit(item: SupplyType) {
    editingId.value = item.id;
    form.value = {
        code: item.code,
        name: item.name,
        description: item.description ?? '',
        sort_order: String(item.sort_order ?? 0),
    };
    showForm.value = true;
}

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: SupplyType[] }>('/catalog/supply-types');
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
        code: form.value.code.trim(),
        name: form.value.name.trim(),
        description: form.value.description.trim() || null,
        sort_order: Number(form.value.sort_order) || 0,
    };
    try {
        if (editingId.value) {
            await api(`/catalog/supply-types/${editingId.value}`, { method: 'PUT', body: JSON.stringify(body) });
        } else {
            await api('/catalog/supply-types', { method: 'POST', body: JSON.stringify(body) });
        }
        const wasEdit = Boolean(editingId.value);
        showForm.value = false;
        resetForm();
        toast.success(wasEdit ? 'Tipo de insumo actualizado.' : 'Tipo de insumo creado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        saving.value = false;
    }
}

async function remove(id: number) {
    if (!window.confirm('¿Eliminar este tipo de insumo?')) {
        return;
    }
    try {
        await api(`/catalog/supply-types/${id}`, { method: 'DELETE' });
        toast.success('Tipo eliminado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

onMounted(load);
</script>

<template>
    <div class="portal-page">
        <SectionSubnav :items="catalogSuppliesSectionNav" />
        <div class="flex flex-wrap items-start justify-between gap-3">
            <PageHeader
                class="flex-1"
                title="Tipos de insumo"
                subtitle="Taxonomía de refacciones y consumibles."
            />
            <AppButton v-if="canWrite" type="button" class="shrink-0" @click="openCreate">
                Nuevo tipo
            </AppButton>
        </div>
        <ReadOnlyNotice v-if="!canWrite" module-label="Tipos de insumo" />

        <p v-if="loading" class="text-portal-muted">Cargando…</p>
        <div v-else class="portal-table-wrap">
            <table class="portal-data-table">
                <thead>
                    <tr class="border-b">
                        <th class="py-2">Código</th>
                        <th>Nombre</th>
                        <th class="w-28" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in items" :key="row.id" class="border-b border-portal-border/60">
                        <td class="py-2 font-mono text-sm">{{ row.code }}</td>
                        <td>{{ row.name }}</td>
                        <td class="text-right">
                            <template v-if="canWrite">
                                <button type="button" class="portal-link mr-2" @click="openEdit(row)">Editar</button>
                                <button type="button" class="portal-link text-red-600" @click="remove(row.id)">
                                    Eliminar
                                </button>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AppModal
            :open="showForm && canWrite"
            :title="editingId ? 'Editar tipo de insumo' : 'Nuevo tipo de insumo'"
            size="sm"
            @close="showForm = false"
        >
            <form id="supply-type-form" class="space-y-4" @submit.prevent="save">
                <MaterialField v-model="form.code" label="Código" required :disabled="Boolean(editingId)" />
                <MaterialField v-model="form.name" label="Nombre" required />
                <MaterialField v-model="form.description" label="Descripción" />
                <MaterialField v-model="form.sort_order" label="Orden" type="number" />
            </form>
            <template #footer>
                <button
                    type="button"
                    class="text-portal-muted rounded-xl px-4 py-2 text-sm hover:bg-white/5"
                    @click="showForm = false"
                >
                    Cancelar
                </button>
                <AppButton type="submit" form="supply-type-form" :disabled="saving">Guardar</AppButton>
            </template>
        </AppModal>
    </div>
</template>
