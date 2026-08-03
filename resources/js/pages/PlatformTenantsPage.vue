<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, getToken } from '@/api/client';
import { useToast } from '@/composables/useToast';
import type { CompanyOption } from '@/stores/auth';
import { useAuthStore } from '@/stores/auth';
import { useCompanyStore } from '@/stores/company';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppModal from '@/components/ui/AppModal.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import UserAvatar from '@/components/ui/UserAvatar.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';
import ConfigurableDataTable from '@/components/ui/ConfigurableDataTable.vue';
import { tableActionsColumn, type TableColumnDef } from '@/lib/tableColumns';

type TenantRow = {
    id: number;
    name: string;
    legal_name: string | null;
    currency: string;
    is_active: boolean;
    active_memberships_count: number;
    logo_url?: string | null;
    admin_user_id?: number | null;
    admin_name?: string | null;
    admin_email?: string | null;
    admin_avatar_url?: string | null;
};

type MembershipCreated = {
    user_id: number;
    email: string;
    name: string;
};

type TenantCreated = TenantRow & {
    admin_user_id?: number;
};

const toast = useToast();
const router = useRouter();
const auth = useAuthStore();
const companyStore = useCompanyStore();
const tenants = ref<TenantRow[]>([]);
const loading = ref(true);
const showCreate = ref(false);
const creating = ref(false);
const selectedTenant = ref<TenantRow | null>(null);
const showAddUser = ref(false);
const addingUser = ref(false);
const showEdit = ref(false);
const savingEdit = ref(false);
const enteringWorkspaceId = ref<number | null>(null);

const editForm = ref({
    name: '',
    legal_name: '',
    admin_name: '',
    admin_email: '',
});

const createLogoFile = ref<File | null>(null);
const createLogoPreview = ref<string | null>(null);
const createLogoInput = ref<HTMLInputElement | null>(null);

const editLogoFile = ref<File | null>(null);
const editLogoPreview = ref<string | null>(null);
const editLogoUrl = ref<string | null>(null);
const editLogoInput = ref<HTMLInputElement | null>(null);

const editAvatarFile = ref<File | null>(null);
const editAvatarPreview = ref<string | null>(null);
const editAvatarUrl = ref<string | null>(null);
const editAvatarInput = ref<HTMLInputElement | null>(null);

const createForm = ref({
    name: '',
    legal_name: '',
    admin_name: '',
    admin_email: '',
});

const userForm = ref({
    name: '',
    email: '',
    role: 'technician',
});

const roleOptions = [
    { value: 'administrator', label: 'Administrador' },
    { value: 'supervisor', label: 'Supervisión' },
    { value: 'technician', label: 'Técnico' },
    { value: 'billing', label: 'Facturación' },
    { value: 'auditor', label: 'Auditor' },
];

const createAvatarFile = ref<File | null>(null);
const createAvatarPreview = ref<string | null>(null);
const createAvatarInput = ref<HTMLInputElement | null>(null);

const userAvatarFile = ref<File | null>(null);
const userAvatarPreview = ref<string | null>(null);
const userAvatarInput = ref<HTMLInputElement | null>(null);

const createDisplayAvatarUrl = computed(() => createAvatarPreview.value);
const createDisplayLogoUrl = computed(() => createLogoPreview.value);
const userDisplayAvatarUrl = computed(() => userAvatarPreview.value);

const editDisplayLogoUrl = computed(() => editLogoPreview.value ?? editLogoUrl.value);
const editDisplayAvatarUrl = computed(() => editAvatarPreview.value ?? editAvatarUrl.value);

function tenantLogoUrl(row: TenantRow): string | null {
    return row.logo_url ?? row.admin_avatar_url ?? null;
}

function resetCreateLogo() {
    if (createLogoPreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(createLogoPreview.value);
    }
    createLogoFile.value = null;
    createLogoPreview.value = null;
}

function resetEditLogo() {
    if (editLogoPreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(editLogoPreview.value);
    }
    editLogoFile.value = null;
    editLogoPreview.value = null;
    editLogoUrl.value = null;
}

function resetEditAvatar() {
    if (editAvatarPreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(editAvatarPreview.value);
    }
    editAvatarFile.value = null;
    editAvatarPreview.value = null;
    editAvatarUrl.value = null;
}

const activeCount = computed(() => tenants.value.filter((t) => t.is_active).length);

const tenantTableColumns: TableColumnDef[] = [
    {
        id: 'avatar',
        label: '',
        locked: true,
        headerClass: 'portal-table-avatar-cell py-2',
        cellClass: 'portal-table-avatar-cell py-2',
    },
    { id: 'company', label: 'Empresa' },
    { id: 'users', label: 'Usuarios', cellClass: 'text-portal-muted' },
    { id: 'status', label: 'Estado' },
    tableActionsColumn({
        headerClass: 'w-0 whitespace-nowrap py-2 pr-1 text-right',
        cellClass: 'w-0 whitespace-nowrap py-2 pr-1 text-right',
    }),
];

function resetCreateAvatar() {
    if (createAvatarPreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(createAvatarPreview.value);
    }
    createAvatarFile.value = null;
    createAvatarPreview.value = null;
}

function resetUserAvatar() {
    if (userAvatarPreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(userAvatarPreview.value);
    }
    userAvatarFile.value = null;
    userAvatarPreview.value = null;
}

async function uploadPlatformTenantLogo(companyId: number, file: File): Promise<void> {
    const body = new FormData();
    body.append('logo', file);
    const headers: Record<string, string> = { Accept: 'application/json' };
    const token = getToken();
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }
    const res = await fetch(`/api/v1/platform/tenants/${companyId}/logo`, { method: 'POST', headers, body });
    const text = await res.text();
    const data = text ? JSON.parse(text) : null;
    if (!res.ok) {
        throw new Error(data?.message ?? res.statusText);
    }
}

function openCreateLogoPicker() {
    createLogoInput.value?.click();
}

function onCreateLogoSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
        return;
    }
    if (createLogoPreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(createLogoPreview.value);
    }
    createLogoFile.value = file;
    createLogoPreview.value = URL.createObjectURL(file);
    input.value = '';
}

function openEditLogoPicker() {
    editLogoInput.value?.click();
}

function onEditLogoSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
        return;
    }
    if (editLogoPreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(editLogoPreview.value);
    }
    editLogoFile.value = file;
    editLogoPreview.value = URL.createObjectURL(file);
    input.value = '';
}

function openEditAvatarPicker() {
    editAvatarInput.value?.click();
}

function onEditAvatarSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
        return;
    }
    if (editAvatarPreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(editAvatarPreview.value);
    }
    editAvatarFile.value = file;
    editAvatarPreview.value = URL.createObjectURL(file);
    input.value = '';
}

async function uploadPlatformUserAvatar(userId: number, file: File): Promise<void> {
    const body = new FormData();
    body.append('avatar', file);
    const headers: Record<string, string> = { Accept: 'application/json' };
    const token = getToken();
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }
    const res = await fetch(`/api/v1/platform/users/${userId}/avatar`, { method: 'POST', headers, body });
    const text = await res.text();
    const data = text ? JSON.parse(text) : null;
    if (!res.ok) {
        throw new Error(data?.message ?? res.statusText);
    }
}

function openCreateAvatarPicker() {
    createAvatarInput.value?.click();
}

function openUserAvatarPicker() {
    userAvatarInput.value?.click();
}

function onCreateAvatarSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
        return;
    }
    if (createAvatarPreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(createAvatarPreview.value);
    }
    createAvatarFile.value = file;
    createAvatarPreview.value = URL.createObjectURL(file);
    input.value = '';
}

function onUserAvatarSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
        return;
    }
    if (userAvatarPreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(userAvatarPreview.value);
    }
    userAvatarFile.value = file;
    userAvatarPreview.value = URL.createObjectURL(file);
    input.value = '';
}

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: TenantRow[] }>('/platform/tenants');
        tenants.value = res.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function createTenant() {
    if (!createForm.value.name.trim() || !createForm.value.admin_email.trim()) {
        toast.warning('Completa nombre de empresa y correo del administrador.');
        return;
    }
    creating.value = true;
    try {
        const res = await api<{ data: TenantCreated }>('/platform/tenants', {
            method: 'POST',
            body: JSON.stringify({
                name: createForm.value.name.trim(),
                legal_name: createForm.value.legal_name.trim() || null,
                admin_name: createForm.value.admin_name.trim() || createForm.value.admin_email.trim(),
                admin_email: createForm.value.admin_email.trim(),
            }),
        });
        if (createAvatarFile.value && res.data.admin_user_id) {
            await uploadPlatformUserAvatar(res.data.admin_user_id, createAvatarFile.value);
        }
        if (createLogoFile.value) {
            await uploadPlatformTenantLogo(res.data.id, createLogoFile.value);
        }
        toast.success('Cliente administrador creado.');
        showCreate.value = false;
        createForm.value = { name: '', legal_name: '', admin_name: '', admin_email: '' };
        resetCreateAvatar();
        resetCreateLogo();
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        creating.value = false;
    }
}

function openAddUser(tenant: TenantRow) {
    selectedTenant.value = tenant;
    userForm.value = { name: '', email: '', role: 'technician' };
    resetUserAvatar();
    showAddUser.value = true;
}

function openEdit(tenant: TenantRow) {
    selectedTenant.value = tenant;
    editForm.value = {
        name: tenant.name,
        legal_name: tenant.legal_name ?? '',
        admin_name: tenant.admin_name ?? '',
        admin_email: tenant.admin_email ?? '',
    };
    resetEditLogo();
    resetEditAvatar();
    editLogoUrl.value = tenant.logo_url ?? null;
    editAvatarUrl.value = tenant.admin_avatar_url ?? null;
    showEdit.value = true;
}

function closeEditModal() {
    showEdit.value = false;
    resetEditLogo();
    resetEditAvatar();
}

async function saveEdit() {
    if (!selectedTenant.value || !editForm.value.name.trim()) {
        toast.warning('Indica el nombre comercial del cliente.');
        return;
    }
    if (!editForm.value.admin_email.trim()) {
        toast.warning('Indica el correo del administrador.');
        return;
    }
    savingEdit.value = true;
    try {
        const res = await api<{ data: TenantRow }>(`/platform/tenants/${selectedTenant.value.id}`, {
            method: 'PATCH',
            body: JSON.stringify({
                name: editForm.value.name.trim(),
                legal_name: editForm.value.legal_name.trim() || null,
                admin_name: editForm.value.admin_name.trim() || editForm.value.admin_email.trim(),
                admin_email: editForm.value.admin_email.trim(),
            }),
        });
        const tenantId = selectedTenant.value.id;
        if (editLogoFile.value) {
            await uploadPlatformTenantLogo(tenantId, editLogoFile.value);
        }
        if (editAvatarFile.value) {
            const adminUserId = res.data.admin_user_id ?? selectedTenant.value.admin_user_id;
            if (adminUserId) {
                await uploadPlatformUserAvatar(adminUserId, editAvatarFile.value);
            }
        }
        const updated = res.data;
        const companyOpt = auth.companies.find((c) => c.id === updated.id);
        if (companyOpt) {
            companyOpt.name = updated.name;
        }
        if (companyStore.current?.id === updated.id) {
            companyStore.select({ ...companyStore.current, name: updated.name });
        }
        toast.success('Cliente actualizado.');
        showEdit.value = false;
        resetEditLogo();
        resetEditAvatar();
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        savingEdit.value = false;
    }
}

async function openTenantWorkspace(tenant: TenantRow) {
    enteringWorkspaceId.value = tenant.id;
    try {
        await api(`/platform/tenants/${tenant.id}/assume`, { method: 'POST' });
        const found: CompanyOption =
            auth.companies.find((c) => c.id === tenant.id) ?? {
                id: tenant.id,
                name: tenant.name,
                role: 'administrator',
                assumed: true,
                company_is_active: tenant.is_active,
            };
        companyStore.select(found);
        await router.push('/app/dashboard');
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        enteringWorkspaceId.value = null;
    }
}

function closeCreateModal() {
    showCreate.value = false;
    resetCreateAvatar();
    resetCreateLogo();
}

function closeAddUserModal() {
    showAddUser.value = false;
    resetUserAvatar();
}

async function addUser() {
    if (!selectedTenant.value || !userForm.value.email.trim()) {
        return;
    }
    addingUser.value = true;
    try {
        const res = await api<{ data: MembershipCreated }>(
            `/platform/tenants/${selectedTenant.value.id}/memberships`,
            {
                method: 'POST',
                body: JSON.stringify({
                    name: userForm.value.name.trim() || userForm.value.email.trim(),
                    email: userForm.value.email.trim(),
                    role: userForm.value.role,
                }),
            },
        );
        if (userAvatarFile.value) {
            await uploadPlatformUserAvatar(res.data.user_id, userAvatarFile.value);
        }
        toast.success('Usuario agregado al cliente.');
        showAddUser.value = false;
        resetUserAvatar();
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        addingUser.value = false;
    }
}

const togglingId = ref<number | null>(null);

async function toggleTenantActive(tenant: TenantRow) {
    togglingId.value = tenant.id;
    try {
        await api(`/platform/tenants/${tenant.id}`, {
            method: 'PATCH',
            body: JSON.stringify({ is_active: !tenant.is_active }),
        });
        toast.success(tenant.is_active ? 'Cliente desactivado.' : 'Cliente activado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        togglingId.value = null;
    }
}

onMounted(load);
</script>

<template>
    <div class="portal-page space-y-4" data-tour="page-platform-tenants">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <PageHeader
                title="Clientes de plataforma"
                subtitle="Empresas tenant (clientes administradores). Cada una opera con datos aislados; tú defines workflows y la plantilla global de roles."
            />
            <AppButton type="button" @click="showCreate = true">Nuevo cliente administrador</AppButton>
        </div>

        <p class="text-portal-muted text-sm">
            {{ activeCount }} cliente(s) activo(s) · {{ tenants.length }} en total
        </p>

        <p v-if="loading" class="text-portal-muted">Cargando…</p>

        <ConfigurableDataTable
            v-else
            table-id="platform-tenants"
            table-class="portal-data-table--platform-tenants"
            :columns="tenantTableColumns"
            :rows="tenants"
            row-key="id"
            export-file-name="clientes-plataforma"
        >
            <template #avatar="{ row }">
                <UserAvatar
                    :name="(row as TenantRow).name"
                    :avatar-url="tenantLogoUrl(row as TenantRow)"
                    image-fit="contain"
                    size="sm"
                />
            </template>
            <template #company="{ row }">
                <p class="text-portal-heading font-medium">{{ (row as TenantRow).name }}</p>
                <p v-if="(row as TenantRow).legal_name" class="text-portal-muted text-xs">
                    {{ (row as TenantRow).legal_name }}
                </p>
            </template>
            <template #users="{ row }">{{ (row as TenantRow).active_memberships_count }}</template>
            <template #status="{ row }">
                <StatusBadge :status="(row as TenantRow).is_active ? 'active' : 'inactive'" />
            </template>
            <template #actions="{ row }">
                <div class="table-row-actions platform-tenant-row-actions">
                    <IconActionButton
                        icon="buildings"
                        label="Abrir workspace del cliente"
                        :disabled="enteringWorkspaceId === (row as TenantRow).id"
                        @click="openTenantWorkspace(row as TenantRow)"
                    />
                    <IconActionButton
                        icon="pencil"
                        label="Editar cliente"
                        @click="openEdit(row as TenantRow)"
                    />
                    <IconActionButton
                        icon="power"
                        :variant="(row as TenantRow).is_active ? 'danger' : 'default'"
                        :label="
                            (row as TenantRow).is_active ? 'Desactivar cliente' : 'Activar cliente'
                        "
                        :disabled="togglingId === (row as TenantRow).id"
                        @click="toggleTenantActive(row as TenantRow)"
                    />
                    <IconActionButton
                        icon="users"
                        label="Agregar usuario"
                        @click="openAddUser(row as TenantRow)"
                    />
                </div>
            </template>
        </ConfigurableDataTable>

        <AppModal :open="showCreate" title="Nuevo cliente administrador" size="md" @close="closeCreateModal">
            <form class="space-y-4" @submit.prevent="createTenant">
                <div class="portal-media-upload">
                    <p class="text-portal-heading text-sm font-medium">Logo del cliente</p>
                    <p class="text-portal-muted mt-0.5 text-xs">
                        Opcional · JPG, PNG o WebP · máx. 2 MB. Se muestra en el listado de clientes.
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-4">
                        <button
                            type="button"
                            class="border-portal-border/50 flex size-16 items-center justify-center overflow-hidden rounded-xl border bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            @click="openCreateLogoPicker"
                        >
                            <img
                                v-if="createDisplayLogoUrl"
                                :src="createDisplayLogoUrl"
                                alt=""
                                class="max-h-full max-w-full object-contain p-1"
                            />
                            <span v-else class="text-portal-muted text-xs">Logo</span>
                        </button>
                        <div class="flex flex-col gap-2">
                            <input
                                ref="createLogoInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="hidden"
                                @change="onCreateLogoSelected"
                            />
                            <AppButton type="button" variant="secondary" @click="openCreateLogoPicker">
                                Elegir logo
                            </AppButton>
                        </div>
                    </div>
                </div>
                <div class="portal-media-upload">
                    <p class="text-portal-heading text-sm font-medium">Foto del administrador</p>
                    <p class="text-portal-muted mt-0.5 text-xs">
                        Opcional · JPG, PNG o WebP · máx. 2 MB.
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-4">
                        <button
                            type="button"
                            class="overflow-hidden rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            @click="openCreateAvatarPicker"
                        >
                            <UserAvatar
                                :name="createForm.admin_name || createForm.admin_email || 'Admin'"
                                :avatar-url="createDisplayAvatarUrl"
                                size="lg"
                            />
                        </button>
                        <div class="flex flex-col gap-2">
                            <input
                                ref="createAvatarInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="hidden"
                                @change="onCreateAvatarSelected"
                            />
                            <AppButton type="button" variant="secondary" @click="openCreateAvatarPicker">
                                Elegir imagen
                            </AppButton>
                            <p v-if="createAvatarFile" class="text-portal-muted text-xs">
                                Se subirá al crear el cliente.
                            </p>
                        </div>
                    </div>
                </div>
                <MaterialField v-model="createForm.name" label="Nombre comercial" required />
                <MaterialField v-model="createForm.legal_name" label="Razón social (opcional)" />
                <MaterialField v-model="createForm.admin_name" label="Nombre del administrador" />
                <MaterialField
                    v-model="createForm.admin_email"
                    label="Correo del administrador"
                    type="email"
                    required
                />
                <p class="text-portal-muted text-xs">
                    Se creará la empresa y una membresía de administrador. La contraseña inicial es la del entorno demo.
                </p>
                <div class="flex justify-end gap-2">
                    <AppButton type="button" variant="secondary" @click="closeCreateModal">Cancelar</AppButton>
                    <AppButton type="submit" :disabled="creating">
                        {{ creating ? 'Creando…' : 'Crear' }}
                    </AppButton>
                </div>
            </form>
        </AppModal>

        <AppModal
            :open="showEdit"
            :title="selectedTenant ? `Editar ${selectedTenant.name}` : 'Editar cliente'"
            size="md"
            @close="closeEditModal"
        >
            <form class="space-y-4" @submit.prevent="saveEdit">
                <div class="portal-media-upload">
                    <p class="text-portal-heading text-sm font-medium">Logo del cliente</p>
                    <p class="text-portal-muted mt-0.5 text-xs">Opcional · JPG, PNG o WebP · máx. 2 MB.</p>
                    <div class="mt-3 flex flex-wrap items-center gap-4">
                        <button
                            type="button"
                            class="border-portal-border/50 flex size-16 items-center justify-center overflow-hidden rounded-xl border bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            @click="openEditLogoPicker"
                        >
                            <img
                                v-if="editDisplayLogoUrl"
                                :src="editDisplayLogoUrl"
                                alt=""
                                class="max-h-full max-w-full object-contain p-1"
                            />
                            <span v-else class="text-portal-muted text-xs">Logo</span>
                        </button>
                        <div class="flex flex-col gap-2">
                            <input
                                ref="editLogoInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="hidden"
                                @change="onEditLogoSelected"
                            />
                            <AppButton type="button" variant="secondary" @click="openEditLogoPicker">
                                Cambiar logo
                            </AppButton>
                        </div>
                    </div>
                </div>
                <div class="portal-media-upload">
                    <p class="text-portal-heading text-sm font-medium">Foto del administrador</p>
                    <p class="text-portal-muted mt-0.5 text-xs">Opcional · JPG, PNG o WebP · máx. 2 MB.</p>
                    <div class="mt-3 flex flex-wrap items-center gap-4">
                        <button
                            type="button"
                            class="overflow-hidden rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            @click="openEditAvatarPicker"
                        >
                            <UserAvatar
                                :name="editForm.admin_name || editForm.admin_email || 'Admin'"
                                :avatar-url="editDisplayAvatarUrl"
                                size="lg"
                            />
                        </button>
                        <div class="flex flex-col gap-2">
                            <input
                                ref="editAvatarInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="hidden"
                                @change="onEditAvatarSelected"
                            />
                            <AppButton type="button" variant="secondary" @click="openEditAvatarPicker">
                                Cambiar foto
                            </AppButton>
                        </div>
                    </div>
                </div>
                <MaterialField v-model="editForm.name" label="Nombre comercial" required />
                <MaterialField v-model="editForm.legal_name" label="Razón social (opcional)" />
                <MaterialField v-model="editForm.admin_name" label="Nombre del administrador" />
                <MaterialField
                    v-model="editForm.admin_email"
                    label="Correo del administrador"
                    type="email"
                    required
                />
                <div class="flex justify-end gap-2">
                    <AppButton type="button" variant="secondary" @click="closeEditModal">Cancelar</AppButton>
                    <AppButton type="submit" :disabled="savingEdit">
                        {{ savingEdit ? 'Guardando…' : 'Guardar' }}
                    </AppButton>
                </div>
            </form>
        </AppModal>

        <AppModal
            :open="showAddUser"
            :title="selectedTenant ? `Usuario en ${selectedTenant.name}` : 'Usuario'"
            size="md"
            @close="closeAddUserModal"
        >
            <form class="space-y-4" @submit.prevent="addUser">
                <div class="portal-media-upload">
                    <p class="text-portal-heading text-sm font-medium">Foto del usuario</p>
                    <p class="text-portal-muted mt-0.5 text-xs">
                        Opcional · JPG, PNG o WebP · máx. 2 MB.
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-4">
                        <button
                            type="button"
                            class="overflow-hidden rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            @click="openUserAvatarPicker"
                        >
                            <UserAvatar
                                :name="userForm.name || userForm.email || 'Usuario'"
                                :avatar-url="userDisplayAvatarUrl"
                                size="lg"
                            />
                        </button>
                        <div class="flex flex-col gap-2">
                            <input
                                ref="userAvatarInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="hidden"
                                @change="onUserAvatarSelected"
                            />
                            <AppButton type="button" variant="secondary" @click="openUserAvatarPicker">
                                Elegir imagen
                            </AppButton>
                            <p v-if="userAvatarFile" class="text-portal-muted text-xs">
                                Se subirá al agregar el usuario.
                            </p>
                        </div>
                    </div>
                </div>
                <MaterialField v-model="userForm.name" label="Nombre" />
                <MaterialField v-model="userForm.email" label="Correo" type="email" required />
                <MaterialSelect v-model="userForm.role" label="Rol" :options="roleOptions" />
                <div class="flex justify-end gap-2">
                    <AppButton type="button" variant="secondary" @click="closeAddUserModal">Cancelar</AppButton>
                    <AppButton type="submit" :disabled="addingUser">
                        {{ addingUser ? 'Guardando…' : 'Agregar' }}
                    </AppButton>
                </div>
            </form>
        </AppModal>
    </div>
</template>
