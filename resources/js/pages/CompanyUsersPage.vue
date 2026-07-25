<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '@/api/client';
import PageHeader from '@/components/ui/PageHeader.vue';
import GlassCard from '@/components/ui/GlassCard.vue';
import AppButton from '@/components/ui/AppButton.vue';

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

const users = ref<UserRow[]>([]);
const roles = ref<RoleOption[]>([]);
const modulesCatalog = ref<ModuleCatalogItem[]>([]);
const loading = ref(true);
const message = ref<string | null>(null);
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
    message.value = null;
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
        message.value = (e as Error).message;
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
    message.value = null;
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
        closePanel();
        await load();
    } catch (e) {
        message.value = (e as Error).message;
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-6">
        <PageHeader
            title="Usuarios de la empresa"
            subtitle="Define rol y, por módulo, acceso de lectura y escritura. Si ambos están apagados, el módulo no aparece en el menú del usuario."
        />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <input
                v-model="search"
                type="search"
                placeholder="Buscar por nombre o correo…"
                class="field-input max-w-xs"
            />
            <AppButton type="button" @click="openCreate">Agregar usuario</AppButton>
        </div>

        <p v-if="loading" class="text-slate-500">Cargando…</p>

        <GlassCard v-else class="overflow-x-auto">
            <table class="w-full min-w-[32rem] text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-500">
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
                        class="border-b border-slate-100 last:border-0"
                    >
                        <td class="py-3 pr-4 font-medium text-slate-900">{{ u.name }}</td>
                        <td class="py-3 pr-4 text-slate-600">{{ u.email }}</td>
                        <td class="py-3 pr-4">{{ u.role_label }}</td>
                        <td class="py-3 pr-4 text-slate-600">{{ visibleModuleCount(u) }}</td>
                        <td class="py-3 pr-4">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="
                                    u.is_active
                                        ? 'bg-emerald-100 text-emerald-800'
                                        : 'bg-slate-200 text-slate-600'
                                "
                            >
                                {{ u.is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="py-3 text-right">
                            <button
                                type="button"
                                class="text-sm font-medium text-primary-600 hover:text-primary-700"
                                @click="openEdit(u)"
                            >
                                Editar
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </GlassCard>

        <p v-if="message" class="text-sm text-red-600">{{ message }}</p>

        <div
            v-if="showPanel"
            class="fixed inset-0 z-30 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center"
            @click.self="closePanel"
        >
            <GlassCard class="max-h-[90vh] w-full max-w-2xl overflow-y-auto" padding="lg">
                <h3 class="text-lg font-semibold text-slate-900">
                    {{ isCreate ? 'Agregar usuario' : 'Editar usuario' }}
                </h3>
                <form class="mt-4 space-y-4" @submit.prevent="save">
                    <label v-if="isCreate" class="block text-sm font-medium text-slate-700">
                        Correo
                        <input
                            v-model="formEmail"
                            type="email"
                            required
                            class="field-input mt-1 w-full"
                        />
                    </label>
                    <label v-if="isCreate" class="block text-sm font-medium text-slate-700">
                        Nombre
                        <input v-model="formName" type="text" class="field-input mt-1 w-full" />
                    </label>
                    <p v-if="!isCreate" class="text-sm text-slate-600">{{ formEmail }}</p>
                    <label class="block text-sm font-medium text-slate-700">
                        Rol
                        <select v-model="formRole" class="field-input mt-1 w-full">
                            <option v-for="r in roles" :key="r.name" :value="r.name">
                                {{ r.label }}
                            </option>
                        </select>
                    </label>
                    <label v-if="!isCreate" class="flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="formActive" type="checkbox" class="rounded border-slate-300" />
                        Acceso activo a esta empresa
                    </label>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Acceso por módulo
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Lectura permite ver; escritura incluye crear y editar. Sin ninguno, el módulo se oculta del
                            menú.
                        </p>
                        <table class="mt-3 w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-slate-500">
                                    <th class="pb-2 pr-2 font-medium">Módulo</th>
                                    <th class="pb-2 pr-2 font-medium">Lectura</th>
                                    <th class="pb-2 font-medium">Escritura</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="mod in editableModules"
                                    :key="mod.id"
                                    class="border-t border-slate-200/80"
                                >
                                    <td class="py-2 pr-2 font-medium text-slate-800">{{ mod.label }}</td>
                                    <td class="py-2 pr-2">
                                        <input
                                            type="checkbox"
                                            :checked="formModules[mod.id]?.read"
                                            @change="
                                                setModuleRead(
                                                    mod.id,
                                                    ($event.target as HTMLInputElement).checked,
                                                )
                                            "
                                        />
                                    </td>
                                    <td class="py-2">
                                        <input
                                            type="checkbox"
                                            :disabled="!mod.supports_write"
                                            :checked="formModules[mod.id]?.write"
                                            @change="
                                                setModuleWrite(
                                                    mod.id,
                                                    ($event.target as HTMLInputElement).checked,
                                                )
                                            "
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            class="rounded-xl px-4 py-2 text-sm text-slate-600 hover:bg-slate-100"
                            @click="closePanel"
                        >
                            Cancelar
                        </button>
                        <AppButton type="submit" :disabled="saving">
                            {{ saving ? 'Guardando…' : 'Guardar' }}
                        </AppButton>
                    </div>
                </form>
            </GlassCard>
        </div>
    </div>
</template>
