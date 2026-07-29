<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { api, getCompanyId, getToken } from '@/api/client';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppModal from '@/components/ui/AppModal.vue';
import AppButton from '@/components/ui/AppButton.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';
import UserAvatar from '@/components/ui/UserAvatar.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';
import ConfigurableDataTable from '@/components/ui/ConfigurableDataTable.vue';
import { tableActionsColumn, type TableColumnDef } from '@/lib/tableColumns';

type ModuleAccessState = { read: boolean; write: boolean; visible: boolean };

type UserRow = {
    id: number;
    membership_id: number;
    name: string;
    email: string;
    avatar_url?: string | null;
    role: string;
    role_label: string;
    is_active: boolean;
    role_permissions: string[];
    extra_permissions: string[];
    effective_permissions: string[];
    modules: Record<string, ModuleAccessState>;
};

type RoleOption = { name: string; label: string; permissions: string[] };

type PermissionEntry = { slug: string; label: string };

type PermissionGroup = {
    module_id: string;
    module_label: string;
    permissions: PermissionEntry[];
};

const toast = useToast();
const users = ref<UserRow[]>([]);
const roles = ref<RoleOption[]>([]);
const permissionGroups = ref<PermissionGroup[]>([]);
const roleOptions = computed(() => roles.value.map((r) => ({ value: r.name, label: r.label })));
const loading = ref(true);

const showPanel = ref(false);
const panelUser = ref<UserRow | null>(null);
const formEmail = ref('');
const formName = ref('');
const formRole = ref('technician');
const formActive = ref(true);
const formExtraPermissions = ref<string[]>([]);
const saving = ref(false);
const isCreate = ref(false);

const avatarFile = ref<File | null>(null);
const avatarPreview = ref<string | null>(null);
const avatarUploading = ref(false);
const formAvatarUrl = ref<string | null>(null);
const avatarInput = ref<HTMLInputElement | null>(null);

const displayAvatarUrl = computed(() => avatarPreview.value ?? formAvatarUrl.value);

const rolePermissionSet = computed(() => {
    const role = roles.value.find((r) => r.name === formRole.value);

    return new Set(role?.permissions ?? []);
});

const grantableGroups = computed(() =>
    permissionGroups.value
        .map((group) => ({
            ...group,
            permissions: group.permissions.filter((p) => !rolePermissionSet.value.has(p.slug)),
        }))
        .filter((g) => g.permissions.length > 0),
);

const companyUserTableColumns: TableColumnDef[] = [
    {
        id: 'avatar',
        label: '',
        locked: true,
        headerClass: 'portal-table-avatar-cell py-2',
        cellClass: 'portal-table-avatar-cell py-2',
    },
    { id: 'name', label: 'Nombre', cellClass: 'text-portal-heading py-3 pr-4 font-medium' },
    { id: 'email', label: 'Correo', cellClass: 'text-portal-muted py-3 pr-4' },
    { id: 'role', label: 'Rol', cellClass: 'text-portal-heading py-3 pr-4' },
    { id: 'extras', label: 'Extras', cellClass: 'text-portal-muted py-3 pr-4' },
    { id: 'modules', label: 'Módulos visibles', cellClass: 'text-portal-muted py-3 pr-4' },
    { id: 'status', label: 'Estado', cellClass: 'py-3 pr-4' },
    tableActionsColumn({ headerClass: 'py-2 font-medium', cellClass: 'py-3 text-right' }),
];

function companyUserRowClass(row: unknown): string {
    return !(row as UserRow).is_active ? 'opacity-60' : '';
}

watch(formRole, () => {
    if (!showPanel.value) {
        return;
    }
    formExtraPermissions.value = formExtraPermissions.value.filter((slug) => !rolePermissionSet.value.has(slug));
});

function resetAvatarState(url: string | null = null) {
    if (avatarPreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(avatarPreview.value);
    }
    avatarFile.value = null;
    avatarPreview.value = null;
    formAvatarUrl.value = url;
}

async function uploadUserAvatar(userId: number, file: File): Promise<UserRow> {
    const body = new FormData();
    body.append('avatar', file);
    const headers: Record<string, string> = { Accept: 'application/json' };
    const token = getToken();
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }
    const companyId = getCompanyId();
    if (companyId) {
        headers['X-Company-Id'] = companyId;
    }
    const res = await fetch(`/api/v1/company/users/${userId}/avatar`, { method: 'POST', headers, body });
    const text = await res.text();
    const data = text ? JSON.parse(text) : null;
    if (!res.ok) {
        throw new Error(data?.message ?? res.statusText);
    }
    return data.data as UserRow;
}

function openAvatarPicker() {
    avatarInput.value?.click();
}

function onAvatarSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
        return;
    }
    if (avatarPreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(avatarPreview.value);
    }
    avatarFile.value = file;
    avatarPreview.value = URL.createObjectURL(file);
    if (panelUser.value) {
        void uploadAvatarForEditing();
    }
    input.value = '';
}

async function uploadAvatarForEditing() {
    if (!panelUser.value || !avatarFile.value) {
        return;
    }
    avatarUploading.value = true;
    try {
        const updated = await uploadUserAvatar(panelUser.value.id, avatarFile.value);
        formAvatarUrl.value = updated.avatar_url ?? null;
        avatarFile.value = null;
        if (avatarPreview.value?.startsWith('blob:')) {
            URL.revokeObjectURL(avatarPreview.value);
        }
        avatarPreview.value = null;
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        avatarUploading.value = false;
    }
}

function toggleExtra(slug: string, checked: boolean) {
    const set = new Set(formExtraPermissions.value);
    if (checked) {
        set.add(slug);
    } else {
        set.delete(slug);
    }
    formExtraPermissions.value = [...set];
}

function isExtraChecked(slug: string): boolean {
    return formExtraPermissions.value.includes(slug);
}

async function load() {
    loading.value = true;
    try {
        const [usersRes, rolesRes] = await Promise.all([
            api<{ data: UserRow[] }>('/company/users'),
            api<{
                data: RoleOption[];
                permission_groups: PermissionGroup[];
            }>('/company/roles'),
        ]);
        users.value = usersRes.data;
        roles.value = rolesRes.data;
        permissionGroups.value = rolesRes.permission_groups ?? [];
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    isCreate.value = true;
    panelUser.value = null;
    resetAvatarState();
    formEmail.value = '';
    formName.value = '';
    formRole.value = 'technician';
    formActive.value = true;
    formExtraPermissions.value = [];
    showPanel.value = true;
}

function openEdit(user: UserRow) {
    isCreate.value = false;
    panelUser.value = user;
    resetAvatarState(user.avatar_url ?? null);
    formEmail.value = user.email;
    formName.value = user.name;
    formRole.value = user.role;
    formActive.value = user.is_active;
    formExtraPermissions.value = [...user.extra_permissions];
    showPanel.value = true;
}

function closePanel() {
    showPanel.value = false;
    resetAvatarState();
}

function visibleModuleCount(user: UserRow) {
    return Object.values(user.modules).filter((m) => m.visible).length;
}

function extraCount(user: UserRow) {
    return user.extra_permissions.length;
}

async function save() {
    saving.value = true;
    try {
        const payload = {
            role: formRole.value,
            extra_permissions: formExtraPermissions.value,
            ...(isCreate.value
                ? {
                      email: formEmail.value,
                      name: formName.value || undefined,
                  }
                : { is_active: formActive.value }),
        };
        if (isCreate.value) {
            const created = await api<{ data: UserRow }>('/company/users', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            if (avatarFile.value) {
                await uploadUserAvatar(created.data.id, avatarFile.value);
            }
        } else if (panelUser.value) {
            await api(`/company/users/${panelUser.value.id}`, {
                method: 'PUT',
                body: JSON.stringify(payload),
            });
        }
        const wasCreate = isCreate.value;
        closePanel();
        toast.success(wasCreate ? 'Usuario invitado.' : 'Usuario actualizado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="portal-page" data-tour="page-company-users">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <PageHeader
                class="flex-1"
                title="Usuarios de la empresa"
                subtitle="Asigna un rol (plantilla global de plataforma) y permisos adicionales solo para esta persona. Los permisos del rol se definen en Plataforma → Roles y permisos."
            />
            <AppButton type="button" class="shrink-0" @click="openCreate">Agregar usuario</AppButton>
        </div>

        <p v-if="loading" class="text-portal-muted">Cargando…</p>

        <ConfigurableDataTable
            v-else
            table-id="company-users"
            table-class="min-w-[32rem]"
            :columns="companyUserTableColumns"
            :rows="users"
            :row-key="(row) => (row as UserRow).membership_id"
            :row-class="companyUserRowClass"
        >
            <template #avatar="{ row }">
                <UserAvatar :name="(row as UserRow).name" :avatar-url="(row as UserRow).avatar_url" size="sm" />
            </template>
            <template #name="{ row }">{{ (row as UserRow).name }}</template>
            <template #email="{ row }">{{ (row as UserRow).email }}</template>
            <template #role="{ row }">{{ (row as UserRow).role_label }}</template>
            <template #extras="{ row }">{{ extraCount(row as UserRow) }}</template>
            <template #modules="{ row }">{{ visibleModuleCount(row as UserRow) }}</template>
            <template #status="{ row }">
                <span
                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="(row as UserRow).is_active ? 'portal-status-active' : 'portal-status-inactive'"
                >
                    {{ (row as UserRow).is_active ? 'Activo' : 'Inactivo' }}
                </span>
            </template>
            <template #actions="{ row }">
                <div class="table-row-actions justify-end">
                    <IconActionButton icon="pencil" label="Editar usuario" @click="openEdit(row as UserRow)" />
                </div>
            </template>
        </ConfigurableDataTable>

        <AppModal
            :open="showPanel"
            :title="isCreate ? 'Agregar usuario' : 'Editar usuario'"
            size="sm"
            @close="closePanel"
        >
            <form id="company-user-form" class="space-y-4" @submit.prevent="save">
                <div class="portal-media-upload">
                <p class="text-portal-heading text-sm font-medium">Foto del usuario</p>
                <p class="text-portal-muted mt-0.5 text-xs">Avatar visible en el menú y listados. JPG, PNG o WebP · máx. 2 MB.</p>
                <div class="mt-3 flex flex-wrap items-center gap-4">
                    <button
                        type="button"
                        class="overflow-hidden rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                        :disabled="avatarUploading"
                        @click="openAvatarPicker"
                    >
                        <UserAvatar
                            :name="formName || formEmail || 'Usuario'"
                            :avatar-url="displayAvatarUrl"
                            size="lg"
                        />
                    </button>
                    <div class="flex flex-col gap-2">
                        <input
                            ref="avatarInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="hidden"
                            :disabled="avatarUploading"
                            @change="onAvatarSelected"
                        />
                        <AppButton
                            type="button"
                            variant="secondary"
                            :disabled="avatarUploading"
                            @click="openAvatarPicker"
                        >
                            {{ avatarUploading ? 'Subiendo…' : 'Elegir imagen' }}
                        </AppButton>
                        <p v-if="isCreate && avatarFile" class="text-portal-muted text-xs">
                            Se subirá al guardar el usuario.
                        </p>
                        <p v-else-if="isCreate" class="text-portal-muted text-xs">
                            Opcional: puedes elegir la foto antes de guardar.
                        </p>
                        <p v-else class="text-portal-muted text-xs">
                            También puedes hacer clic en el círculo para cambiar la imagen.
                        </p>
                    </div>
                </div>
                </div>
                <MaterialField
                    v-if="isCreate"
                    v-model="formEmail"
                    label="Correo"
                    type="email"
                    required
                />
                <MaterialField v-if="isCreate" v-model="formName" label="Nombre" />
                <p v-if="!isCreate" class="text-portal-muted text-sm">{{ formEmail }}</p>
                <MaterialSelect v-model="formRole" label="Rol" :options="roleOptions" />
                <label v-if="!isCreate" class="text-portal-muted flex items-center gap-2 text-sm">
                    <input v-model="formActive" type="checkbox" class="rounded border-white/20" />
                    Acceso activo a esta empresa
                </label>

                <div class="portal-form-panel">
                    <p class="text-portal-muted text-xs font-semibold uppercase tracking-wide">
                        Permisos del rol (solo lectura)
                    </p>
                    <p v-if="rolePermissionSet.size === 0" class="text-portal-muted mt-2 text-xs">Sin permisos base.</p>
                    <ul v-else class="text-portal-muted mt-2 flex flex-wrap gap-1 text-xs">
                        <li
                            v-for="slug in [...rolePermissionSet].sort()"
                            :key="slug"
                            class="rounded bg-white/5 px-2 py-0.5 font-mono"
                        >
                            {{ slug }}
                        </li>
                    </ul>
                </div>

                <div class="portal-form-panel max-h-80 overflow-y-auto">
                    <p class="text-portal-muted text-xs font-semibold uppercase tracking-wide">
                        Permisos adicionales
                    </p>
                    <p class="text-portal-muted mt-1 text-xs">
                        Solo aparecen permisos que el rol seleccionado no incluye ya. Se suman al rol para el menú y la API.
                    </p>
                    <p v-if="grantableGroups.length === 0" class="text-portal-muted mt-3 text-sm">
                        Este rol ya incluye todos los permisos de la plataforma.
                    </p>
                    <div v-for="group in grantableGroups" :key="group.module_id" class="mt-4">
                        <p class="text-portal-heading text-sm font-medium">{{ group.module_label }}</p>
                        <ul class="mt-2 space-y-2">
                            <li
                                v-for="perm in group.permissions"
                                :key="perm.slug"
                                class="text-portal-muted flex items-start gap-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    class="mt-1 rounded border-portal-border"
                                    :checked="isExtraChecked(perm.slug)"
                                    @change="
                                        toggleExtra(perm.slug, ($event.target as HTMLInputElement).checked)
                                    "
                                />
                                <span>
                                    <span class="text-portal-heading">{{ perm.label }}</span>
                                    <span class="ml-1 font-mono text-xs opacity-70">{{ perm.slug }}</span>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </form>
            <template #footer>
                <button
                    type="button"
                    class="text-portal-muted rounded-xl px-4 py-2 text-sm hover:bg-white/5"
                    @click="closePanel"
                >
                    Cancelar
                </button>
                <AppButton type="submit" form="company-user-form" :disabled="saving">
                    {{ saving ? 'Guardando…' : 'Guardar' }}
                </AppButton>
            </template>
        </AppModal>
    </div>
</template>
