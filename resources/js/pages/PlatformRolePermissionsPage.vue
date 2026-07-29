<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppButton from '@/components/ui/AppButton.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';

type RoleRow = {
    name: string;
    label: string;
    permissions: string[];
    locked: boolean;
};

type PermissionEntry = { slug: string; label: string };

type PermissionGroup = {
    module_id: string;
    module_label: string;
    permissions: PermissionEntry[];
};

const toast = useToast();
const loading = ref(true);
const saving = ref(false);
const roles = ref<RoleRow[]>([]);
const permissionGroups = ref<PermissionGroup[]>([]);
const selectedRole = ref('supervisor');
const draft = ref<Record<string, string[]>>({});

const roleOptions = computed(() =>
    roles.value.map((r) => ({ value: r.name, label: r.label })),
);

const currentRole = computed(() => roles.value.find((r) => r.name === selectedRole.value) ?? null);

const selectedPermissions = computed({
    get(): Set<string> {
        const list = draft.value[selectedRole.value] ?? [];

        return new Set(list);
    },
    set(_value: Set<string>) {
        // handled via toggle
    },
});

function isChecked(slug: string): boolean {
    return selectedPermissions.value.has(slug);
}

function toggle(slug: string, on: boolean) {
    if (currentRole.value?.locked) {
        return;
    }
    const role = selectedRole.value;
    const set = new Set(draft.value[role] ?? []);
    if (on) {
        set.add(slug);
    } else {
        set.delete(slug);
    }
    draft.value = { ...draft.value, [role]: [...set].sort() };
}

async function load() {
    loading.value = true;
    try {
        const res = await api<{
            data: {
                roles: RoleRow[];
                permission_groups: PermissionGroup[];
            };
        }>('/platform/role-permissions');
        roles.value = res.data.roles;
        permissionGroups.value = res.data.permission_groups ?? [];
        const map: Record<string, string[]> = {};
        for (const r of res.data.roles) {
            map[r.name] = [...r.permissions];
        }
        draft.value = map;
        if (!map[selectedRole.value] && res.data.roles[0]) {
            selectedRole.value = res.data.roles[0].name;
        }
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    try {
        const res = await api<{
            data: { roles: RoleRow[] };
            message?: string;
        }>('/platform/role-permissions', {
            method: 'PUT',
            body: JSON.stringify({ roles: draft.value }),
        });
        roles.value = res.data.roles;
        const map: Record<string, string[]> = {};
        for (const r of res.data.roles) {
            map[r.name] = [...r.permissions];
        }
        draft.value = map;
        toast.success(res.message ?? 'Plantilla guardada.');
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="portal-page space-y-4" data-tour="page-platform-roles">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <PageHeader
                title="Roles de plataforma"
                subtitle="Define los permisos de cada rol base para todas las empresas. Los administradores de empresa solo asignan rol y permisos extra por usuario."
            />
            <AppButton type="button" :disabled="saving || loading" @click="save">Guardar plantilla</AppButton>
        </div>

        <p v-if="loading" class="text-portal-muted">Cargando…</p>

        <template v-else>
            <MaterialSelect v-model="selectedRole" label="Rol a editar" :options="roleOptions" />

            <p v-if="currentRole?.locked" class="portal-callout portal-callout--warning">
                El rol Administrador incluye siempre todos los permisos del catálogo.
            </p>

            <div class="portal-form-panel max-h-[32rem] overflow-y-auto p-4">
                <div v-for="group in permissionGroups" :key="group.module_id" class="mb-6 last:mb-0">
                    <p class="text-portal-heading text-sm font-semibold">{{ group.module_label }}</p>
                    <ul class="mt-2 space-y-2">
                        <li
                            v-for="perm in group.permissions"
                            :key="perm.slug"
                            class="flex items-start gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                class="mt-1 rounded border-portal-border"
                                :checked="isChecked(perm.slug)"
                                :disabled="currentRole?.locked"
                                @change="toggle(perm.slug, ($event.target as HTMLInputElement).checked)"
                            />
                            <span class="text-portal-muted">
                                <span class="text-portal-heading">{{ perm.label }}</span>
                                <span class="ml-1 font-mono text-xs opacity-70">{{ perm.slug }}</span>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </template>
    </div>
</template>
