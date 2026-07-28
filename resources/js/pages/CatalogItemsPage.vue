<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useToast } from '@/composables/useToast';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';
import SectionSubnav from '@/components/ui/SectionSubnav.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppModal from '@/components/ui/AppModal.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';
import AppButton from '@/components/ui/AppButton.vue';
import { catalogEquipmentSectionNav } from '@/lib/sectionNav';

type EquipmentTypeRef = { id: number; code: string; name: string };

type CatalogItem = {
    id: number;
    code: string;
    name: string;
    manufacturer?: string | null;
    equipment_type_id?: number | null;
    equipment_type?: EquipmentTypeRef | null;
};

const { canWriteModule } = useModuleAccess();
const toast = useToast();
const canWrite = computed(() => canWriteModule('catalog_items'));

const items = ref<CatalogItem[]>([]);
const equipmentTypes = ref<EquipmentTypeRef[]>([]);
const filterTypeId = ref('');
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const editingId = ref<number | null>(null);

const form = ref({ equipment_type_id: '', code: '', name: '', manufacturer: '' });

const filteredItems = computed(() => {
    if (!filterTypeId.value) {
        return items.value;
    }
    const id = Number(filterTypeId.value);
    return items.value.filter((i) => i.equipment_type_id === id);
});

const typeFilterOptions = computed(() => [
    { value: '', label: 'Todos los tipos' },
    ...equipmentTypes.value.map((t) => ({ value: String(t.id), label: t.name })),
]);

const typeFormOptions = computed(() =>
    equipmentTypes.value.map((t) => ({ value: String(t.id), label: `${t.name} (${t.code})` })),
);

function resetForm() {
    form.value = {
        equipment_type_id: equipmentTypes.value[0] ? String(equipmentTypes.value[0].id) : '',
        code: '',
        name: '',
        manufacturer: '',
    };
    editingId.value = null;
}

function openCreate() {
    resetForm();
    showForm.value = true;
}

function openEdit(item: CatalogItem) {
    editingId.value = item.id;
    form.value = {
        equipment_type_id: item.equipment_type_id ? String(item.equipment_type_id) : '',
        code: item.code,
        name: item.name,
        manufacturer: item.manufacturer ?? '',
    };
    showForm.value = true;
}

async function load() {
    loading.value = true;
    try {
        const [itemsRes, typesRes] = await Promise.all([
            api<{ data: CatalogItem[] }>('/catalog/items'),
            api<{ data: EquipmentTypeRef[] }>('/catalog/equipment-types'),
        ]);
        items.value = itemsRes.data;
        equipmentTypes.value = typesRes.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function save() {
    if (!form.value.equipment_type_id) {
        toast.warning('Selecciona un tipo de equipo.');
        return;
    }
    saving.value = true;
    try {
        const body = {
            equipment_type_id: Number(form.value.equipment_type_id),
            code: form.value.code,
            name: form.value.name,
            manufacturer: form.value.manufacturer || null,
        };
        if (editingId.value) {
            await api(`/catalog/items/${editingId.value}`, { method: 'PUT', body: JSON.stringify(body) });
        } else {
            await api('/catalog/items', { method: 'POST', body: JSON.stringify(body) });
        }
        const wasEdit = Boolean(editingId.value);
        showForm.value = false;
        resetForm();
        toast.success(wasEdit ? 'Equipo actualizado.' : 'Equipo creado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        saving.value = false;
    }
}

async function remove(id: number) {
    if (!window.confirm('¿Eliminar este ítem del catálogo?')) {
        return;
    }
    try {
        await api(`/catalog/items/${id}`, { method: 'DELETE' });
        toast.success('Equipo eliminado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

onMounted(load);
</script>

<template>
    <div class="portal-page" data-tour="page-catalog-items">
        <SectionSubnav :items="catalogEquipmentSectionNav" />
        <div class="flex flex-wrap items-start justify-between gap-3">
            <PageHeader class="flex-1" title="Equipos" subtitle="Catálogo maestro de equipos y fabricantes." />
            <AppButton v-if="canWrite" type="button" class="shrink-0" @click="openCreate">
                Nuevo equipo
            </AppButton>
        </div>
        <ReadOnlyNotice v-if="!canWrite" module-label="Equipos" />

        <div v-if="!loading" class="mb-4 max-w-xs">
            <MaterialSelect v-model="filterTypeId" label="Filtrar por tipo" :options="typeFilterOptions" />
        </div>

        <p v-if="loading" class="text-portal-muted">Cargando…</p>
        <div v-else class="portal-table-wrap">
            <table class="portal-data-table">
                <thead>
                    <tr class="border-b">
                        <th class="py-2">Código</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Fabricante</th>
                        <th v-if="canWrite" class="w-32" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in filteredItems" :key="item.id" class="border-b">
                        <td class="text-portal-heading py-2 font-mono text-xs">{{ item.code }}</td>
                        <td class="text-portal-heading">{{ item.name }}</td>
                        <td class="text-portal-muted text-sm">{{ item.equipment_type?.name ?? '—' }}</td>
                        <td class="text-portal-muted">{{ item.manufacturer ?? '—' }}</td>
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
            :title="editingId ? 'Editar equipo' : 'Nuevo equipo'"
            size="sm"
            @close="showForm = false"
        >
            <form id="catalog-item-form" class="space-y-4" @submit.prevent="save">
                <MaterialSelect
                    v-model="form.equipment_type_id"
                    label="Tipo de equipo"
                    required
                    :options="typeFormOptions"
                />
                <MaterialField v-model="form.code" label="Código" required />
                <MaterialField v-model="form.name" label="Nombre" required />
                <MaterialField v-model="form.manufacturer" label="Fabricante" />
            </form>
            <template #footer>
                <button
                    type="button"
                    class="text-portal-muted rounded-xl px-4 py-2 text-sm hover:bg-white/5"
                    @click="showForm = false"
                >
                    Cancelar
                </button>
                <AppButton type="submit" form="catalog-item-form" :disabled="saving">Guardar</AppButton>
            </template>
        </AppModal>
    </div>
</template>
