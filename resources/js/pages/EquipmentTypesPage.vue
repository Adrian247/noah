<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useToast } from '@/composables/useToast';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import SectionSubnav from '@/components/ui/SectionSubnav.vue';
import AppModal from '@/components/ui/AppModal.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';
import AppButton from '@/components/ui/AppButton.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';
import ConfigurableDataTable from '@/components/ui/ConfigurableDataTable.vue';
import { tableActionsColumn, type TableColumnDef } from '@/lib/tableColumns';
import { catalogEquipmentSectionNav } from '@/lib/sectionNav';

type FormPick = { id: number; name: string; slug: string };

type EquipmentType = {
    id: number;
    code: string;
    name: string;
    description?: string | null;
    sort_order?: number;
    default_form_definition_id?: number | null;
    default_form_definition?: { id: number; name: string; slug: string } | null;
};

const { canWriteModule } = useModuleAccess();
const toast = useToast();
const canWrite = computed(() => canWriteModule('catalog_items'));

const equipmentTypeTableColumns = computed((): TableColumnDef[] => {
    const cols: TableColumnDef[] = [
        { id: 'code', label: 'Código', cellClass: 'py-2 font-mono text-sm' },
        { id: 'name', label: 'Nombre' },
        { id: 'form', label: 'Formulario de ficha', cellClass: 'text-portal-muted text-sm' },
    ];
    if (canWrite.value) {
        cols.push(tableActionsColumn({ cellClass: 'text-right' }));
    }
    return cols;
});

const items = ref<EquipmentType[]>([]);
const equipmentForms = ref<FormPick[]>([]);
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const editingId = ref<number | null>(null);

const form = ref({
    code: '',
    name: '',
    description: '',
    sort_order: '0',
    default_form_definition_id: '',
});

const formOptions = computed(() => [
    { value: '', label: '— Sin formulario —' },
    ...equipmentForms.value.map((f) => ({ value: String(f.id), label: f.name })),
]);

function resetForm() {
    form.value = {
        code: '',
        name: '',
        description: '',
        sort_order: '0',
        default_form_definition_id: '',
    };
    editingId.value = null;
}

function openCreate() {
    resetForm();
    showForm.value = true;
}

function openEdit(item: EquipmentType) {
    editingId.value = item.id;
    form.value = {
        code: item.code,
        name: item.name,
        description: item.description ?? '',
        sort_order: String(item.sort_order ?? 0),
        default_form_definition_id: item.default_form_definition_id
            ? String(item.default_form_definition_id)
            : '',
    };
    showForm.value = true;
}

async function load() {
    loading.value = true;
    try {
        const [typesRes, formsRes] = await Promise.all([
            api<{ data: EquipmentType[] }>('/catalog/equipment-types'),
            api<{ data: FormPick[] }>('/catalog/equipment-types/form-options'),
        ]);
        items.value = typesRes.data;
        equipmentForms.value = formsRes.data;
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
        default_form_definition_id: form.value.default_form_definition_id
            ? Number(form.value.default_form_definition_id)
            : null,
    };
    try {
        if (editingId.value) {
            await api(`/catalog/equipment-types/${editingId.value}`, { method: 'PUT', body: JSON.stringify(body) });
        } else {
            await api('/catalog/equipment-types', { method: 'POST', body: JSON.stringify(body) });
        }
        const wasEdit = Boolean(editingId.value);
        showForm.value = false;
        resetForm();
        toast.success(wasEdit ? 'Tipo de artículo actualizado.' : 'Tipo de artículo creado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        saving.value = false;
    }
}

async function remove(id: number) {
    if (!window.confirm('¿Eliminar este tipo de equipo?')) {
        return;
    }
    try {
        await api(`/catalog/equipment-types/${id}`, { method: 'DELETE' });
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
        <SectionSubnav :items="catalogEquipmentSectionNav" />
        <div class="flex flex-wrap items-start justify-between gap-3">
            <PageHeader
                class="flex-1"
                title="Tipos de artículo"
                subtitle="Clasificación de equipos en catálogo (vehículo, motor, bomba, …)."
            />
            <AppButton v-if="canWrite" type="button" class="shrink-0" @click="openCreate">
                Nuevo tipo
            </AppButton>
        </div>
        <ReadOnlyNotice v-if="!canWrite" module-label="Tipos de artículo" />

        <p v-if="loading" class="text-portal-muted">Cargando…</p>
        <ConfigurableDataTable
            v-else
            table-id="catalog-equipment-types"
            :columns="equipmentTypeTableColumns"
            :rows="items"
            row-key="id"
        >
            <template #code="{ row }">{{ (row as EquipmentType).code }}</template>
            <template #name="{ row }">{{ (row as EquipmentType).name }}</template>
            <template #form="{ row }">{{ (row as EquipmentType).default_form_definition?.name ?? '—' }}</template>
            <template #actions="{ row }">
                <div class="table-row-actions">
                    <IconActionButton icon="pencil" label="Editar tipo de equipo" @click="openEdit(row as EquipmentType)" />
                    <IconActionButton
                        icon="trash"
                        label="Eliminar tipo de equipo"
                        variant="danger"
                        @click="remove((row as EquipmentType).id)"
                    />
                </div>
            </template>
        </ConfigurableDataTable>

        <AppModal
            :open="showForm && canWrite"
            :title="editingId ? 'Editar tipo de equipo' : 'Nuevo tipo de equipo'"
            size="sm"
            @close="showForm = false"
        >
            <form id="equipment-type-form" class="space-y-4" @submit.prevent="save">
                <MaterialField v-model="form.code" label="Código" required :disabled="Boolean(editingId)" />
                <MaterialField v-model="form.name" label="Nombre" required />
                <MaterialField v-model="form.description" label="Descripción" />
                <MaterialSelect
                    v-model="form.default_form_definition_id"
                    label="Formulario de ficha (equipo)"
                    :options="formOptions"
                />
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
                <AppButton type="submit" form="equipment-type-form" :disabled="saving">Guardar</AppButton>
            </template>
        </AppModal>
    </div>
</template>
