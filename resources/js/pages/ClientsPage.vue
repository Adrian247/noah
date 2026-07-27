<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppModal from '@/components/ui/AppModal.vue';
import AppButton from '@/components/ui/AppButton.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import UserAvatar from '@/components/ui/UserAvatar.vue';
import { getToken, getCompanyId } from '@/api/client';

type Client = {
    id: number;
    code?: string | null;
    legal_name: string;
    trade_name?: string | null;
    logo_url?: string | null;
    tax_id?: string | null;
    billing_email?: string | null;
    billing_address?: string | null;
    is_active: boolean;
};

const { canWriteModule, isVisible } = useModuleAccess();
const toast = useToast();
const canEdit = computed(() => canWriteModule('clients'));
const canView = computed(() => isVisible('clients'));

const clients = ref<Client[]>([]);
const loading = ref(true);
const showForm = ref(false);
const editingId = ref<number | null>(null);
const form = ref({
    code: '',
    legal_name: '',
    trade_name: '',
    tax_id: '',
    billing_email: '',
    billing_address: '',
    is_active: true,
});
const logoFile = ref<File | null>(null);
const logoPreview = ref<string | null>(null);
const logoUploading = ref(false);
const formLogoUrl = ref<string | null>(null);
const logoInput = ref<HTMLInputElement | null>(null);

const displayLogoUrl = computed(() => logoPreview.value ?? formLogoUrl.value);

function resetLogoState(url: string | null = null) {
    if (logoPreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(logoPreview.value);
    }
    logoFile.value = null;
    logoPreview.value = null;
    formLogoUrl.value = url;
}

async function uploadClientLogo(clientId: number, file: File): Promise<Client> {
    const body = new FormData();
    body.append('logo', file);
    const headers: Record<string, string> = { Accept: 'application/json' };
    const token = getToken();
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }
    const companyId = getCompanyId();
    if (companyId) {
        headers['X-Company-Id'] = companyId;
    }
    const res = await fetch(`/api/v1/clients/${clientId}/logo`, { method: 'POST', headers, body });
    const text = await res.text();
    const data = text ? JSON.parse(text) : null;
    if (!res.ok) {
        throw new Error(data?.message ?? res.statusText);
    }
    return data.data as Client;
}

function openLogoPicker() {
    logoInput.value?.click();
}

function onLogoSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
        return;
    }
    if (logoPreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(logoPreview.value);
    }
    logoFile.value = file;
    logoPreview.value = URL.createObjectURL(file);
    if (editingId.value) {
        void uploadLogoForEditing();
    }
    input.value = '';
}

async function uploadLogoForEditing() {
    if (!editingId.value || !logoFile.value) {
        return;
    }
    logoUploading.value = true;
    try {
        const updated = await uploadClientLogo(editingId.value, logoFile.value);
        formLogoUrl.value = updated.logo_url ?? null;
        logoFile.value = null;
        if (logoPreview.value?.startsWith('blob:')) {
            URL.revokeObjectURL(logoPreview.value);
        }
        logoPreview.value = null;
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        logoUploading.value = false;
    }
}

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: Client[] }>('/clients');
        clients.value = res.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editingId.value = null;
    resetLogoState();
    form.value = {
        code: '',
        legal_name: '',
        trade_name: '',
        tax_id: '',
        billing_email: '',
        billing_address: '',
        is_active: true,
    };
    showForm.value = true;
}

function openEdit(c: Client) {
    editingId.value = c.id;
    resetLogoState(c.logo_url ?? null);
    form.value = {
        code: c.code ?? '',
        legal_name: c.legal_name,
        trade_name: c.trade_name ?? '',
        tax_id: c.tax_id ?? '',
        billing_email: c.billing_email ?? '',
        billing_address: c.billing_address ?? '',
        is_active: c.is_active,
    };
    showForm.value = true;
}

async function save() {
    if (!canEdit.value) {
        return;
    }
    const body = {
        code: form.value.code || null,
        legal_name: form.value.legal_name,
        trade_name: form.value.trade_name || null,
        tax_id: form.value.tax_id || null,
        billing_email: form.value.billing_email || null,
        billing_address: form.value.billing_address || null,
        is_active: form.value.is_active,
    };
    try {
        if (editingId.value) {
            await api(`/clients/${editingId.value}`, { method: 'PUT', body: JSON.stringify(body) });
        } else {
            const created = await api<{ data: Client }>('/clients', {
                method: 'POST',
                body: JSON.stringify(body),
            });
            if (logoFile.value) {
                await uploadClientLogo(created.data.id, logoFile.value);
            }
        }
        const wasEdit = Boolean(editingId.value);
        showForm.value = false;
        resetLogoState();
        toast.success(wasEdit ? 'Cliente actualizado.' : 'Cliente creado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function deactivate(c: Client) {
    if (!canEdit.value || !confirm(`¿Desactivar a ${c.legal_name}?`)) {
        return;
    }
    try {
        await api(`/clients/${c.id}`, { method: 'DELETE' });
        toast.success('Cliente desactivado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

onMounted(load);
</script>

<template>
    <div class="portal-page">
        <div v-if="!canView">
            <PageHeader
                title="Clientes"
                subtitle="Clientes de facturación: datos fiscales y contacto para prefacturas y emisión."
            />
            <p class="text-portal-muted text-sm">No tienes acceso al módulo de clientes.</p>
        </div>

        <template v-else>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <PageHeader
                    class="flex-1"
                    title="Clientes"
                    subtitle="Clientes de facturación: datos fiscales y contacto para prefacturas y emisión."
                />
                <AppButton v-if="canEdit" type="button" class="shrink-0" @click="openCreate">
                    Nuevo cliente
                </AppButton>
            </div>
            <p v-if="!canEdit" class="text-portal-muted text-sm">
                Solo lectura. Pide a un administrador permiso para editar clientes y subir imágenes.
            </p>

        <p v-if="loading" class="text-portal-muted">Cargando…</p>

        <div v-else class="portal-table-wrap">
            <table class="portal-data-table min-w-[36rem]">
                <thead>
                    <tr class="border-b">
                        <th class="w-12 py-2 pr-3" />
                        <th class="py-2 pr-3">Código</th>
                        <th class="py-2 pr-3">Razón social</th>
                        <th class="py-2 pr-3">RFC</th>
                        <th class="py-2 pr-3">Estado</th>
                        <th v-if="canEdit" class="py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="c in clients"
                        :key="c.id"
                        class="border-b"
                        :class="!c.is_active ? 'opacity-60' : ''"
                    >
                        <td class="py-2 pr-3">
                            <UserAvatar
                                :name="c.legal_name"
                                :avatar-url="c.logo_url"
                                size="sm"
                            />
                        </td>
                        <td class="text-portal-muted py-2 pr-3 font-mono text-xs">{{ c.code ?? '—' }}</td>
                        <td class="py-2 pr-3">
                            <p class="text-portal-heading font-medium">{{ c.legal_name }}</p>
                            <p v-if="c.trade_name" class="text-portal-muted text-xs">{{ c.trade_name }}</p>
                        </td>
                        <td class="text-portal-muted py-2 pr-3">{{ c.tax_id ?? '—' }}</td>
                        <td class="text-portal-muted py-2 pr-3">
                            {{ c.is_active ? 'Activo' : 'Inactivo' }}
                        </td>
                        <td v-if="canEdit" class="py-2 text-right">
                            <button
                                type="button"
                                class="text-portal-link mr-2"
                                @click="openEdit(c)"
                            >
                                Editar
                            </button>
                            <button
                                v-if="c.is_active"
                                type="button"
                                class="text-red-400 hover:text-red-300"
                                @click="deactivate(c)"
                            >
                                Desactivar
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="!loading && clients.length === 0" class="text-portal-muted text-sm">No hay clientes registrados.</p>


        <AppModal
            :open="showForm && canEdit"
            :title="editingId ? 'Editar cliente' : 'Nuevo cliente'"
            size="sm"
            @close="showForm = false"
        >
            <div class="portal-media-upload">
                <p class="text-portal-heading text-sm font-medium">Imagen del cliente</p>
                <p class="text-portal-muted mt-0.5 text-xs">Logo o foto (como avatar). JPG, PNG o WebP · máx. 2 MB.</p>
                <div class="mt-3 flex flex-wrap items-center gap-4">
                    <button
                        type="button"
                        class="rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                        :disabled="logoUploading"
                        @click="openLogoPicker"
                    >
                        <UserAvatar :name="form.legal_name || 'Cliente'" :avatar-url="displayLogoUrl" size="lg" />
                    </button>
                    <div class="flex flex-col gap-2">
                        <input
                            ref="logoInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="hidden"
                            :disabled="logoUploading"
                            @change="onLogoSelected"
                        />
                        <AppButton type="button" variant="secondary" :disabled="logoUploading" @click="openLogoPicker">
                            {{ logoUploading ? 'Subiendo…' : 'Elegir imagen' }}
                        </AppButton>
                        <p v-if="!editingId && logoFile" class="text-portal-muted text-xs">
                            Se subirá al guardar el cliente.
                        </p>
                        <p v-else-if="editingId" class="text-portal-muted text-xs">
                            También puedes hacer clic en el círculo para cambiar la imagen.
                        </p>
                    </div>
                </div>
            </div>
            <form id="client-form" class="mt-6 space-y-4" @submit.prevent="save">
                <MaterialField v-model="form.legal_name" label="Razón social" required />
                <MaterialField v-model="form.trade_name" label="Nombre comercial" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <MaterialField v-model="form.code" label="Código" />
                    <MaterialField v-model="form.tax_id" label="RFC / ID fiscal" />
                </div>
                <MaterialField v-model="form.billing_email" label="Correo facturación" type="email" />
                <MaterialField v-model="form.billing_address" label="Dirección fiscal" multiline :rows="2" />
                <label v-if="editingId" class="text-portal-muted flex items-center gap-2 text-sm">
                    <input v-model="form.is_active" type="checkbox" />
                    Activo
                </label>
            </form>
            <template #footer>
                <button
                    type="button"
                    class="text-portal-muted rounded-xl px-4 py-2 text-sm hover:bg-white/5"
                    @click="showForm = false"
                >
                    Cancelar
                </button>
                <AppButton type="submit" form="client-form">Guardar</AppButton>
            </template>
        </AppModal>
        </template>
    </div>
</template>
