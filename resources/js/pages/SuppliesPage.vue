<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import SectionSubnav from '@/components/ui/SectionSubnav.vue';
import AppModal from '@/components/ui/AppModal.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';
import AppButton from '@/components/ui/AppButton.vue';
import { catalogSuppliesSectionNav } from '@/lib/sectionNav';

type SupplyTypeRef = { id: number; code: string; name: string };

type SupplyItem = {
    id: number;
    sku: string;
    name: string;
    unit?: string | null;
    standard_cost?: string | number | null;
    supply_type_id?: number | null;
    supply_type?: SupplyTypeRef | null;
    specifications?: { marca?: string; referencia_oem?: string } | null;
};

const { canWriteModule } = useModuleAccess();
const toast = useToast();
const canWrite = computed(() => canWriteModule('catalog_supplies'));

const items = ref<SupplyItem[]>([]);
const supplyTypes = ref<SupplyTypeRef[]>([]);
const filterTypeId = ref('');
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const editingId = ref<number | null>(null);

const form = ref({
    supply_type_id: '',
    sku: '',
    name: '',
    unit: 'pza',
    standard_cost: '',
    marca: '',
    referencia_oem: '',
});

const filteredItems = computed(() => {
    if (!filterTypeId.value) {
        return items.value;
    }
    const id = Number(filterTypeId.value);
    return items.value.filter((i) => i.supply_type_id === id);
});

const typeFilterOptions = computed(() => [
    { value: '', label: 'Todos los tipos' },
    ...supplyTypes.value.map((t) => ({ value: String(t.id), label: t.name })),
]);

const typeFormOptions = computed(() =>
    supplyTypes.value.map((t) => ({ value: String(t.id), label: `${t.name} (${t.code})` })),
);

function resetForm() {
    form.value = {
        supply_type_id: supplyTypes.value[0] ? String(supplyTypes.value[0].id) : '',
        sku: '',
        name: '',
        unit: 'pza',
        standard_cost: '',
        marca: '',
        referencia_oem: '',
    };
    editingId.value = null;
}

function openCreate() {
    resetForm();
    showForm.value = true;
}

function openEdit(item: SupplyItem) {
    editingId.value = item.id;
    form.value = {
        supply_type_id: item.supply_type_id ? String(item.supply_type_id) : '',
        sku: item.sku,
        name: item.name,
        unit: item.unit ?? '',
        standard_cost: item.standard_cost != null ? String(item.standard_cost) : '',
        marca: item.specifications?.marca ?? '',
        referencia_oem: item.specifications?.referencia_oem ?? '',
    };
    showForm.value = true;
}

async function load() {
    loading.value = true;
    try {
        const [itemsRes, typesRes] = await Promise.all([
            api<{ data: SupplyItem[] }>('/inventory/supplies'),
            api<{ data: SupplyTypeRef[] }>('/catalog/supply-types'),
        ]);
        items.value = itemsRes.data;
        supplyTypes.value = typesRes.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function save() {
    if (!form.value.supply_type_id) {
        toast.warning('Selecciona un tipo de insumo.');
        return;
    }
    saving.value = true;
    const specifications =
        form.value.marca || form.value.referencia_oem
            ? {
                  ...(form.value.marca ? { marca: form.value.marca } : {}),
                  ...(form.value.referencia_oem ? { referencia_oem: form.value.referencia_oem } : {}),
              }
            : null;
    const body = {
        supply_type_id: Number(form.value.supply_type_id),
        sku: form.value.sku,
        name: form.value.name,
        unit: form.value.unit || null,
        standard_cost: form.value.standard_cost ? Number(form.value.standard_cost) : null,
        specifications,
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
    <div class="portal-page" data-tour="page-catalog-supplies">
        <SectionSubnav :items="catalogSuppliesSectionNav" />
        <div class="flex flex-wrap items-start justify-between gap-3">
            <PageHeader class="flex-1" title="Insumos" subtitle="Catálogo de materiales consumibles en rutinas." />
            <AppButton v-if="canWrite" type="button" class="shrink-0" @click="openCreate">
                Nuevo insumo
            </AppButton>
        </div>
        <p v-if="!canWrite" class="text-portal-muted text-sm">Solo lectura: no puedes crear ni editar insumos.</p>

        <div v-if="!loading" class="mb-4 max-w-xs">
            <MaterialSelect v-model="filterTypeId" label="Filtrar por tipo" :options="typeFilterOptions" />
        </div>

        <p v-if="loading" class="text-portal-muted">Cargando…</p>
        <div v-else class="portal-table-wrap">
            <table class="portal-data-table">
                <thead>
                    <tr class="border-b">
                        <th class="py-2">SKU</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Unidad</th>
                        <th>Costo</th>
                        <th v-if="canWrite" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in filteredItems" :key="item.id" class="border-b">
                        <td class="text-portal-heading py-2 font-mono text-xs">{{ item.sku }}</td>
                        <td class="text-portal-heading">{{ item.name }}</td>
                        <td class="text-portal-muted text-sm">{{ item.supply_type?.name ?? '—' }}</td>
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
                <MaterialSelect
                    v-model="form.supply_type_id"
                    label="Tipo de insumo"
                    required
                    :options="typeFormOptions"
                />
                <MaterialField v-model="form.sku" label="SKU" required />
                <MaterialField v-model="form.name" label="Nombre" required />
                <MaterialField v-model="form.unit" label="Unidad" />
                <MaterialField v-model="form.standard_cost" label="Costo estándar" type="number" />
                <MaterialField v-model="form.marca" label="Marca (spec.)" />
                <MaterialField v-model="form.referencia_oem" label="Referencia OEM (spec.)" />
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
