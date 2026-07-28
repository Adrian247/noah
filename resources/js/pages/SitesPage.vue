<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useToast } from '@/composables/useToast';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppModal from '@/components/ui/AppModal.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import AppButton from '@/components/ui/AppButton.vue';

type Site = { id: number; name: string; address?: string | null };

const { canWriteModule } = useModuleAccess();
const toast = useToast();
const canWrite = computed(() => canWriteModule('sites'));

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
    if (!window.confirm('¿Eliminar sitio?')) {
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
            <PageHeader class="flex-1" title="Sitios" subtitle="Ubicaciones físicas asociadas a rutinas y activos." />
            <AppButton v-if="canWrite" type="button" class="shrink-0" @click="openCreate">
                Nuevo sitio
            </AppButton>
        </div>
        <p v-if="loading" class="text-portal-muted text-sm">Cargando…</p>
        <div v-else class="portal-table-wrap">
            <table class="portal-data-table">
                <thead>
                    <tr class="border-b">
                        <th class="py-3">Nombre</th>
                        <th class="py-3">Dirección</th>
                        <th v-if="canWrite" class="py-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in sites" :key="s.id" class="border-b">
                        <td class="text-portal-heading py-3">{{ s.name }}</td>
                        <td class="text-portal-muted py-3">{{ s.address ?? '—' }}</td>
                        <td v-if="canWrite" class="space-x-2 py-3 text-right">
                            <button type="button" class="text-portal-link text-sm underline" @click="openEdit(s)">
                                Editar
                            </button>
                            <button type="button" class="text-sm text-red-400" @click="remove(s.id)">Borrar</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="!loading && sites.length === 0" class="text-portal-muted text-sm">No hay sitios registrados.</p>
        <ReadOnlyNotice v-if="!canWrite" module-label="Sitios" />

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
