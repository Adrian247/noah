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
    modules: Record<string, ModuleAccessState>;
};

type RoleOption = { name: string; label: string; permissions: string[] };

type ModuleCatalogItem = {
    id: string;
    label: string;
    supports_write: boolean;
};

const toast = useToast();
const users = ref<UserRow[]>([]);
const roles = ref<RoleOption[]>([]);
const roleOptions = computed(() => roles.value.map((r) => ({ value: r.name, label: r.label })));
const modulesCatalog = ref<ModuleCatalogItem[]>([]);
const loading = ref(true);
const search = ref('');

const showPanel = ref(false);
const panelUser = ref<UserRow | null>(null);
const formEmail = ref('');
const formName = ref('');
const formRole = ref('technician');
const formActive = ref(true);
const formModules = ref<Record<string, { read: boolean; write: boolean }>>({});
const saving = ref(false);
const isCreate = ref(false);

const editableModules = computed(() =>
    modulesCatalog.value.filter((m) => m.id !== 'dashboard'),
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

function defaultModulesFromRole(roleName: string): Record<string, { read: boolean; write: boolean }> {
    const role = roles.value.find((r) => r.name === roleName);
    const perms = new Set(role?.permissions ?? []);
    const out: Record<string, { read: boolean; write: boolean }> = {};
    for (const mod of editableModules.value) {
        const read = moduleHasReadFromPerms(mod.id, perms);
        const write = moduleHasWriteFromPerms(mod.id, perms);
        out[mod.id] = { read: read || write, write };
    }
    return out;
}

function moduleHasReadFromPerms(moduleId: string, perms: Set<string>): boolean {
    const map: Record<string, string[]> = {
        routines: ['routines.execute', 'routines.assign', 'routines.validate', 'costs.view'],
        assets: ['assets.view', 'assets.manage'],
        catalog_items: ['catalog.view', 'catalog.manage'],
        catalog_supplies: ['catalog.view', 'catalog.manage'],
        catalog_suppliers: ['catalog.suppliers.view', 'catalog.suppliers.manage'],
        clients: ['clients.view', 'clients.manage'],
        sites: ['sites.view', 'sites.manage'],
        design_routine_types: ['design.forms.view', 'design.forms'],
        design_forms: ['design.forms.view', 'design.forms'],
        design_reports: ['design.reports.view', 'design.reports'],
        design_workflows: ['design.workflows.view', 'design.workflows'],
        billing: ['billing.draft', 'billing.draft.edit', 'billing.issue', 'billing.settings', 'costs.view'],
        audit: ['audit.view'],
        company_users: ['company.users.manage'],
    };
    const slugs = map[moduleId] ?? [];
    return slugs.some((p) => perms.has(p));
}

function moduleHasWriteFromPerms(moduleId: string, perms: Set<string>): boolean {
    const map: Record<string, string[]> = {
        routines: ['routines.assign', 'routines.validate'],
        assets: ['assets.manage'],
        catalog_items: ['catalog.manage'],
        catalog_supplies: ['catalog.manage'],
        catalog_suppliers: ['catalog.suppliers.manage'],
        clients: ['clients.manage'],
        sites: ['sites.manage'],
        design_routine_types: ['design.forms'],
        design_forms: ['design.forms'],
        design_reports: ['design.reports'],
        design_workflows: ['design.workflows'],
        billing: ['billing.draft.edit', 'billing.issue', 'billing.settings'],
        audit: [],
        company_users: ['company.users.manage'],
    };
    const slugs = map[moduleId] ?? [];
    return slugs.some((p) => perms.has(p));
}

watch(formRole, (role) => {
    if (!showPanel.value || !isCreate.value) {
        return;
    }
    formModules.value = defaultModulesFromRole(role);
});

function loadModulesIntoForm(source: Record<string, ModuleAccessState>) {
    const out: Record<string, { read: boolean; write: boolean }> = {};
    for (const mod of editableModules.value) {
        const entry = source[mod.id];
        out[mod.id] = {
            read: entry?.read ?? false,
            write: entry?.write ?? false,
        };
    }
    formModules.value = out;
}

async function load() {
    loading.value = true;
    try {
        const [usersRes, rolesRes] = await Promise.all([
            api<{ data: UserRow[] }>('/company/users'),
            api<{
                data: RoleOption[];
                modules_catalog: ModuleCatalogItem[];
            }>('/company/roles'),
        ]);
        users.value = usersRes.data;
        roles.value = rolesRes.data;
        modulesCatalog.value = rolesRes.modules_catalog ?? [];
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
    formModules.value = defaultModulesFromRole(formRole.value);
    showPanel.value = true;
}

function openEdit(user: UserRow) {
    isCreate.value = false;
    panelUser.value = user;
    formEmail.value = user.email;
    formName.value = user.name;
    formRole.value = user.role;
    formActive.value = user.is_active;
    loadModulesIntoForm(user.modules);
    showPanel.value = true;
}

function closePanel() {
    showPanel.value = false;
}

function setModuleRead(moduleId: string, read: boolean) {
    const current = formModules.value[moduleId] ?? { read: false, write: false };
    let write = current.write;
    if (!read) {
        write = false;
    }
    formModules.value = {
        ...formModules.value,
        [moduleId]: { read, write },
    };
}

function setModuleWrite(moduleId: string, write: boolean) {
    const current = formModules.value[moduleId] ?? { read: false, write: false };
    formModules.value = {
        ...formModules.value,
        [moduleId]: { read: write ? true : current.read, write },
    };
}

function visibleModuleCount(user: UserRow) {
    return Object.values(user.modules).filter((m) => m.visible).length;
}

async function save() {
    saving.value = true;
    try {
        const modules = { ...formModules.value };
        if (isCreate.value) {
            await api('/company/users', {
                method: 'POST',
                body: JSON.stringify({
                    email: formEmail.value,
                    name: formName.value || undefined,
                    role: formRole.value,
                    modules,
                }),
            });
        } else if (panelUser.value) {
            await api(`/company/users/${panelUser.value.id}`, {
                method: 'PUT',
                body: JSON.stringify({
                    role: formRole.value,
                    is_active: formActive.value,
                    modules,
                }),
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
    <div class="portal-page">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <PageHeader
                class="flex-1"
                title="Usuarios de la empresa"
                subtitle="Define rol y, por módulo, acceso de lectura y escritura. Si ambos están apagados, el módulo no aparece en el menú del usuario."
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
                        Acceso por módulo
                    </p>
                    <p class="text-portal-muted mt-1 text-xs">
                        Lectura permite ver; escritura incluye crear y editar. Sin ninguno, el módulo se oculta del
                        menú.
                    </p>
                    <table class="portal-data-table mt-3">
                        <thead>
                            <tr class="text-left text-xs">
                                <th class="pb-2 pr-2 font-medium">Módulo</th>
                                <th class="pb-2 pr-2 font-medium">Lectura</th>
                                <th class="pb-2 font-medium">Escritura</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="mod in editableModules" :key="mod.id" class="border-t">
                                <td class="text-portal-heading py-2 pr-2 font-medium">{{ mod.label }}</td>
                                <td class="py-2 pr-2">
                                    <input
                                        type="checkbox"
                                        :checked="formModules[mod.id]?.read"
                                        @change="
                                            setModuleRead(mod.id, ($event.target as HTMLInputElement).checked)
                                        "
                                    />
                                </td>
                                <td class="py-2">
                                    <input
                                        type="checkbox"
                                        :disabled="!mod.supports_write"
                                        :checked="formModules[mod.id]?.write"
                                        @change="
                                            setModuleWrite(mod.id, ($event.target as HTMLInputElement).checked)
                                        "
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
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
