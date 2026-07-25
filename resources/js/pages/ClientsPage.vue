<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import PageHeader from '@/components/ui/PageHeader.vue';
import GlassCard from '@/components/ui/GlassCard.vue';
import AppButton from '@/components/ui/AppButton.vue';
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
const canEdit = computed(() => canWriteModule('clients'));
const canView = computed(() => isVisible('clients'));

const clients = ref<Client[]>([]);
const loading = ref(true);
const message = ref<string | null>(null);
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
    message.value = null;
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
        message.value = (e as Error).message;
    } finally {
        logoUploading.value = false;
    }
}

async function load() {
    loading.value = true;
    message.value = null;
    try {
        const res = await api<{ data: Client[] }>('/clients');
        clients.value = res.data;
    } catch (e) {
        message.value = (e as Error).message;
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
    message.value = null;
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
        showForm.value = false;
        resetLogoState();
        await load();
    } catch (e) {
        message.value = (e as Error).message;
    }
}

async function deactivate(c: Client) {
    if (!canEdit.value || !confirm(`¿Desactivar a ${c.legal_name}?`)) {
        return;
    }
    try {
        await api(`/clients/${c.id}`, { method: 'DELETE' });
        await load();
    } catch (e) {
        message.value = (e as Error).message;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-6">
        <PageHeader
            title="Clientes"
            subtitle="Clientes de facturación: datos fiscales y contacto para prefacturas y emisión."
        />

        <p v-if="!canView" class="text-sm text-slate-500">No tienes acceso al módulo de clientes.</p>

        <template v-else>
        <div v-if="canEdit" class="flex justify-end">
            <AppButton type="button" @click="openCreate">Nuevo cliente</AppButton>
        </div>
        <p v-else class="text-sm text-slate-500">
            Solo lectura. Pide a un administrador permiso para editar clientes y subir imágenes.
        </p>

        <p v-if="loading" class="text-slate-500">Cargando…</p>

        <GlassCard v-else class="overflow-x-auto">
            <table class="w-full min-w-[36rem] text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-500">
                        <th class="py-2 pr-3 w-12" />
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
                        class="border-b border-slate-100"
                        :class="!c.is_active ? 'opacity-60' : ''"
                    >
                        <td class="py-2 pr-3">
                            <UserAvatar
                                :name="c.legal_name"
                                :avatar-url="c.logo_url"
                                size="sm"
                            />
                        </td>
                        <td class="py-2 pr-3 font-mono text-xs">{{ c.code ?? '—' }}</td>
                        <td class="py-2 pr-3">
                            <p class="font-medium text-slate-900">{{ c.legal_name }}</p>
                            <p v-if="c.trade_name" class="text-xs text-slate-500">{{ c.trade_name }}</p>
                        </td>
                        <td class="py-2 pr-3">{{ c.tax_id ?? '—' }}</td>
                        <td class="py-2 pr-3">
                            {{ c.is_active ? 'Activo' : 'Inactivo' }}
                        </td>
                        <td v-if="canEdit" class="py-2 text-right">
                            <button
                                type="button"
                                class="mr-2 text-primary-600 hover:text-primary-700"
                                @click="openEdit(c)"
                            >
                                Editar
                            </button>
                            <button
                                v-if="c.is_active"
                                type="button"
                                class="text-red-600 hover:text-red-700"
                                @click="deactivate(c)"
                            >
                                Desactivar
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </GlassCard>

        <p v-if="message" class="text-sm text-red-600">{{ message }}</p>

        <div
            v-if="showForm && canEdit"
            class="fixed inset-0 z-30 flex items-center justify-center bg-slate-900/40 p-4"
            @click.self="showForm = false"
        >
            <GlassCard class="max-h-[90vh] w-full max-w-lg overflow-y-auto" padding="lg">
                <h3 class="text-lg font-semibold">{{ editingId ? 'Editar cliente' : 'Nuevo cliente' }}</h3>
                <div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
                    <p class="text-sm font-medium text-slate-800">Imagen del cliente</p>
                    <p class="mt-0.5 text-xs text-slate-500">Logo o foto (como avatar). JPG, PNG o WebP · máx. 2 MB.</p>
                    <div class="mt-3 flex flex-wrap items-center gap-4">
                        <button
                            type="button"
                            class="rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            :disabled="logoUploading"
                            @click="openLogoPicker"
                        >
                            <UserAvatar
                                :name="form.legal_name || 'Cliente'"
                                :avatar-url="displayLogoUrl"
                                size="lg"
                            />
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
                            <AppButton
                                type="button"
                                variant="secondary"
                                :disabled="logoUploading"
                                @click="openLogoPicker"
                            >
                                {{ logoUploading ? 'Subiendo…' : 'Elegir imagen' }}
                            </AppButton>
                            <p v-if="!editingId && logoFile" class="text-xs text-primary-700">
                                Se subirá al guardar el cliente.
                            </p>
                            <p v-else-if="editingId" class="text-xs text-slate-500">
                                También puedes hacer clic en el círculo para cambiar la imagen.
                            </p>
                        </div>
                    </div>
                </div>
                <form class="mt-4 space-y-3" @submit.prevent="save">
                    <label class="block text-sm">
                        Razón social
                        <input v-model="form.legal_name" required class="field-input mt-1 w-full" />
                    </label>
                    <label class="block text-sm">
                        Nombre comercial
                        <input v-model="form.trade_name" class="field-input mt-1 w-full" />
                    </label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-sm">
                            Código
                            <input v-model="form.code" class="field-input mt-1 w-full" />
                        </label>
                        <label class="block text-sm">
                            RFC / ID fiscal
                            <input v-model="form.tax_id" class="field-input mt-1 w-full" />
                        </label>
                    </div>
                    <label class="block text-sm">
                        Correo facturación
                        <input v-model="form.billing_email" type="email" class="field-input mt-1 w-full" />
                    </label>
                    <label class="block text-sm">
                        Dirección fiscal
                        <textarea v-model="form.billing_address" rows="2" class="field-input mt-1 w-full" />
                    </label>
                    <label v-if="editingId" class="flex items-center gap-2 text-sm">
                        <input v-model="form.is_active" type="checkbox" />
                        Activo
                    </label>
                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            class="rounded-xl px-4 py-2 text-sm text-slate-600 hover:bg-slate-100"
                            @click="showForm = false"
                        >
                            Cancelar
                        </button>
                        <AppButton type="submit">Guardar</AppButton>
                    </div>
                </form>
            </GlassCard>
        </div>
        </template>
    </div>
</template>
