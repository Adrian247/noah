<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppModal from '@/components/ui/AppModal.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import AppButton from '@/components/ui/AppButton.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';
import ConfigurableDataTable from '@/components/ui/ConfigurableDataTable.vue';
import { tableActionsColumn, type TableColumnDef } from '@/lib/tableColumns';

type Site = { id: number; name: string; address?: string | null };

const { canWriteModule } = useModuleAccess();
const toast = useToast();
const confirm = useConfirm();
const canWrite = computed(() => canWriteModule('sites'));

const siteTableColumns = computed((): TableColumnDef[] => {
    const cols: TableColumnDef[] = [
        { id: 'name', label: 'Nombre', cellClass: 'text-portal-heading py-3' },
        { id: 'address', label: 'Dirección', cellClass: 'text-portal-muted py-3' },
    ];
    if (canWrite.value) {
        cols.push(tableActionsColumn({ headerClass: 'py-3', cellClass: 'table-row-actions py-3' }));
    }
    return cols;
});

const sites = ref<Site[]>([]);
const loading = ref(true);
const showForm = ref(false);
const form = ref({ name: '', address: '' });
const editingId = ref<number | null>(null);

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: Site[] }>('/sites');
        sites.value = res.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

function resetForm() {
    form.value = { name: '', address: '' };
    editingId.value = null;
}

function openCreate() {
    resetForm();
    showForm.value = true;
}

function openEdit(site: Site) {
    editingId.value = site.id;
    form.value = { name: site.name, address: site.address ?? '' };
    showForm.value = true;
}

async function save() {
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
        const wasEdit = Boolean(editingId.value);
        showForm.value = false;
        resetForm();
        toast.success(wasEdit ? 'Sitio actualizado.' : 'Sitio creado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function remove(id: number) {
    const accepted = await confirm('¿Eliminar este sitio? Esta acción no se puede deshacer.', {
        title: 'Eliminar sitio',
        confirmLabel: 'Eliminar',
        danger: true,
    });
    if (!accepted) {
        return;
    }
    try {
        await api(`/sites/${id}`, { method: 'DELETE' });
        toast.success('Sitio eliminado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

onMounted(load);
</script>

<template>
    <div class="portal-page" data-tour="page-sites">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <PageHeader class="flex-1" title="Sitios" subtitle="Ubicaciones físicas asociados a servicios y activos." />
            <AppButton v-if="canWrite" type="button" class="shrink-0" @click="openCreate">
                Nuevo sitio
            </AppButton>
        </div>
        <p v-if="loading" class="text-portal-muted text-sm">Cargando…</p>
        <ConfigurableDataTable
            v-else
            table-id="sites"
            :columns="siteTableColumns"
            :rows="sites"
            row-key="id"
            empty-text="No hay sitios registrados."
        >
            <template #name="{ row }">{{ (row as Site).name }}</template>
            <template #address="{ row }">{{ (row as Site).address ?? '—' }}</template>
            <template #actions="{ row }">
                <IconActionButton icon="pencil" label="Editar sitio" @click="openEdit(row as Site)" />
                <IconActionButton
                    icon="trash"
                    label="Borrar sitio"
                    variant="danger"
                    @click="remove((row as Site).id)"
                />
            </template>
        </ConfigurableDataTable>
        <ReadOnlyNotice v-if="!loading && !canWrite" module-label="Sitios" />

        <AppModal
            :open="showForm && canWrite"
            :title="editingId ? 'Editar sitio' : 'Nuevo sitio'"
            size="sm"
            @close="showForm = false"
        >
            <form id="site-form" class="space-y-4" @submit.prevent="save">
                <MaterialField v-model="form.name" label="Nombre" required />
                <MaterialField v-model="form.address" label="Dirección" />
            </form>
            <template #footer>
                <button
                    type="button"
                    class="text-portal-muted rounded-xl px-4 py-2 text-sm hover:bg-white/5"
                    @click="showForm = false"
                >
                    Cancelar
                </button>
                <AppButton type="submit" form="site-form">Guardar</AppButton>
            </template>
        </AppModal>
    </div>
</template>
