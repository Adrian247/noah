<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppModal from '@/components/ui/AppModal.vue';
import AppButton from '@/components/ui/AppButton.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';

type ModuleAccessState = { read: boolean; write: boolean; visible: boolean };

type UserRow = {
    id: number;
    membership_id: number;
    name: string;
    email: string;
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
const search = ref('');

const showPanel = ref(false);
const panelUser = ref<UserRow | null>(null);
const formEmail = ref('');
const formName = ref('');
const formRole = ref('technician');
const formActive = ref(true);
const formExtraPermissions = ref<string[]>([]);
const saving = ref(false);
const isCreate = ref(false);

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

const filteredUsers = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) {
        return users.value;
    }
    return users.value.filter(
        (u) =>
            u.name.toLowerCase().includes(q) ||
            u.email.toLowerCase().includes(q) ||
            u.role_label.toLowerCase().includes(q),
    );
});

watch(formRole, () => {
    if (!showPanel.value) {
        return;
    }
    formExtraPermissions.value = formExtraPermissions.value.filter((slug) => !rolePermissionSet.value.has(slug));
});

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
    formEmail.value = user.email;
    formName.value = user.name;
    formRole.value = user.role;
    formActive.value = user.is_active;
    formExtraPermissions.value = [...user.extra_permissions];
    showPanel.value = true;
}

function closePanel() {
    showPanel.value = false;
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
            await api('/company/users', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
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

        <MaterialField v-model="search" label="Buscar por nombre o correo" class="max-w-md" />

        <p v-if="loading" class="text-portal-muted">Cargando…</p>

        <div v-else class="portal-table-wrap">
            <table class="portal-data-table min-w-[32rem]">
                <thead>
                    <tr class="border-b">
                        <th class="py-2 pr-4 font-medium">Nombre</th>
                        <th class="py-2 pr-4 font-medium">Correo</th>
                        <th class="py-2 pr-4 font-medium">Rol</th>
                        <th class="py-2 pr-4 font-medium">Extras</th>
                        <th class="py-2 pr-4 font-medium">Módulos visibles</th>
                        <th class="py-2 pr-4 font-medium">Estado</th>
                        <th class="py-2 font-medium" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="u in filteredUsers"
                        :key="u.membership_id"
                        class="border-b last:border-0"
                    >
                        <td class="text-portal-heading py-3 pr-4 font-medium">{{ u.name }}</td>
                        <td class="text-portal-muted py-3 pr-4">{{ u.email }}</td>
                        <td class="text-portal-heading py-3 pr-4">{{ u.role_label }}</td>
                        <td class="text-portal-muted py-3 pr-4">{{ extraCount(u) }}</td>
                        <td class="text-portal-muted py-3 pr-4">{{ visibleModuleCount(u) }}</td>
                        <td class="py-3 pr-4">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="u.is_active ? 'portal-status-active' : 'portal-status-inactive'"
                            >
                                {{ u.is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="py-3 text-right">
                            <button
                                type="button"
                                class="text-portal-link text-sm font-medium underline"
                                @click="openEdit(u)"
                            >
                                Editar
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AppModal :open="showPanel" :title="isCreate ? 'Agregar usuario' : 'Editar usuario'" @close="closePanel">
            <form id="company-user-form" class="space-y-4" @submit.prevent="save">
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
