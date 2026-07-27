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

type Supplier = {
    id: number;
    code: string;
    name: string;
    contact_email?: string | null;
    contact_phone?: string | null;
};

const { canWriteModule } = useModuleAccess();
const toast = useToast();
const canWrite = computed(() => canWriteModule('catalog_suppliers'));

const suppliers = ref<Supplier[]>([]);
const loading = ref(true);
const showForm = ref(false);
const form = ref({ code: '', name: '', contact_email: '', contact_phone: '' });
const editingId = ref<number | null>(null);

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: Supplier[] }>('/catalog/suppliers');
        suppliers.value = res.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

function resetForm() {
    form.value = { code: '', name: '', contact_email: '', contact_phone: '' };
    editingId.value = null;
}

function openCreate() {
    resetForm();
    showForm.value = true;
}

function openEdit(s: Supplier) {
    editingId.value = s.id;
    form.value = {
        code: s.code,
        name: s.name,
        contact_email: s.contact_email ?? '',
        contact_phone: s.contact_phone ?? '',
    };
    showForm.value = true;
}

async function save() {
    const body = {
        code: form.value.code,
        name: form.value.name,
        contact_email: form.value.contact_email || null,
        contact_phone: form.value.contact_phone || null,
    };
    try {
        if (editingId.value) {
            await api(`/catalog/suppliers/${editingId.value}`, { method: 'PUT', body: JSON.stringify(body) });
        } else {
            await api('/catalog/suppliers', { method: 'POST', body: JSON.stringify(body) });
        }
        const wasEdit = Boolean(editingId.value);
        showForm.value = false;
        resetForm();
        toast.success(wasEdit ? 'Proveedor actualizado.' : 'Proveedor creado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

onMounted(load);
</script>

<template>
    <div class="portal-page">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <PageHeader class="flex-1" title="Proveedores" subtitle="Contactos de abastecimiento." />
            <AppButton v-if="canWrite" type="button" class="shrink-0" @click="openCreate">
                Nuevo proveedor
            </AppButton>
        </div>
        <p v-if="loading" class="text-portal-muted">Cargando…</p>
        <div v-else class="portal-table-wrap">
            <table class="portal-data-table">
                <thead>
                    <tr class="border-b">
                        <th class="py-2">Código</th>
                        <th>Nombre</th>
                        <th>Contacto</th>
                        <th v-if="canWrite" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in suppliers" :key="s.id" class="border-b">
                        <td class="text-portal-heading py-2 font-mono text-xs">{{ s.code }}</td>
                        <td class="text-portal-heading">{{ s.name }}</td>
                        <td class="text-portal-muted">{{ s.contact_email ?? s.contact_phone ?? '—' }}</td>
                        <td v-if="canWrite" class="text-right">
                            <button type="button" class="text-portal-link text-sm underline" @click="openEdit(s)">
                                Editar
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="!loading && suppliers.length === 0" class="text-portal-muted text-sm">No hay proveedores registrados.</p>
        <ReadOnlyNotice v-if="!canWrite" module-label="Proveedores" />

        <AppModal
            :open="showForm && canWrite"
            :title="editingId ? 'Editar proveedor' : 'Nuevo proveedor'"
            size="sm"
            @close="showForm = false"
        >
            <form id="supplier-form" class="grid gap-4 sm:grid-cols-2" @submit.prevent="save">
                <MaterialField v-model="form.code" label="Código" required />
                <MaterialField v-model="form.name" label="Nombre" required />
                <MaterialField v-model="form.contact_email" label="Email" type="email" />
                <MaterialField v-model="form.contact_phone" label="Teléfono" />
            </form>
            <template #footer>
                <button
                    type="button"
                    class="text-portal-muted rounded-xl px-4 py-2 text-sm hover:bg-white/5"
                    @click="showForm = false"
                >
                    Cancelar
                </button>
                <AppButton type="submit" form="supplier-form">Guardar</AppButton>
            </template>
        </AppModal>
    </div>
</template>
