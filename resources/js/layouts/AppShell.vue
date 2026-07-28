<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { RouterLink, RouterView, useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useCompanyStore } from '@/stores/company';
import { useSidebarCollapsed } from '@/composables/useSidebarCollapsed';
import { usePermissions } from '@/composables/usePermissions';
import { useModuleAccess } from '@/composables/useModuleAccess';
import UserAvatar from '@/components/ui/UserAvatar.vue';
import NavIcon, { type NavIconName } from '@/components/ui/NavIcon.vue';
import NoahBrand from '@/components/ui/NoahBrand.vue';
import BacteriumNetwork from '@/components/BacteriumNetwork.vue';
import AppAtmosphere from '@/components/AppAtmosphere.vue';
import AppToastHost from '@/components/ui/AppToastHost.vue';
import ProductTour from '@/components/onboarding/ProductTour.vue';
import { useTheme } from '@/composables/useTheme';

type NavItem = {
    to: string;
    label: string;
    icon: NavIconName;
    match?: 'exact' | 'prefix';
    moduleId?: string;
    tourAnchor?: string;
};

const { can } = usePermissions();
const { isVisible, canWriteModule } = useModuleAccess();
const auth = useAuthStore();
const company = useCompanyStore();
const router = useRouter();
const route = useRoute();
const { collapsed, toggle } = useSidebarCollapsed();
const avatarMenuOpen = ref(false);
const avatarUploading = ref(false);
const avatarError = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const avatarMenuRef = ref<HTMLElement | null>(null);

const { isDark } = useTheme();
const companyName = computed(() => company.current?.name ?? 'Empresa');

function filterNavItems(items: NavItem[]): NavItem[] {
    return items.filter((item) => {
        if (!item.moduleId) {
            return true;
        }
        return isVisible(item.moduleId);
    });
}

const navGroups = computed(() => {
    const groups: { label: string; items: NavItem[] }[] = [
        {
            label: 'Operación',
            items: filterNavItems([
                { to: '/app/dashboard', label: 'Inicio', icon: 'home', moduleId: 'dashboard' },
                { to: '/app/routines', label: 'Rutinas', icon: 'clipboard-list', moduleId: 'routines', tourAnchor: 'nav-routines' },
                { to: '/app/assets', label: 'Activos', icon: 'cube', moduleId: 'assets', tourAnchor: 'nav-assets' },
            ]),
        },
        {
            label: 'Catálogos',
            items: filterNavItems([
                { to: '/app/catalog/items', label: 'Equipos', icon: 'package', moduleId: 'catalog_items', tourAnchor: 'nav-catalog-items' },
                { to: '/app/catalog/supplies', label: 'Insumos', icon: 'layers', moduleId: 'catalog_supplies', tourAnchor: 'nav-catalog-supplies' },
                { to: '/app/catalog/suppliers', label: 'Proveedores', icon: 'truck', moduleId: 'catalog_suppliers', tourAnchor: 'nav-catalog-suppliers' },
                { to: '/app/catalog/clients', label: 'Clientes', icon: 'building', moduleId: 'clients', tourAnchor: 'nav-clients' },
                { to: '/app/sites', label: 'Sitios', icon: 'map-pin', moduleId: 'sites', tourAnchor: 'nav-sites' },
            ]),
        },
        {
            label: 'Diseño',
            items: filterNavItems([
                { to: '/app/design/forms', label: 'Formularios', icon: 'document', moduleId: 'design_forms', tourAnchor: 'nav-design-forms' },
                { to: '/app/design/reports', label: 'Reportes', icon: 'chart-bar', moduleId: 'design_reports', tourAnchor: 'nav-design-reports' },
                { to: '/app/design/workflows', label: 'Workflows', icon: 'workflow', moduleId: 'design_workflows', tourAnchor: 'nav-design-workflows' },
            ]),
        },
        {
            label: 'Facturación',
            items: filterNavItems([
                {
                    to: '/app/billing',
                    label: 'Facturas',
                    icon: 'receipt',
                    match: 'exact',
                    moduleId: 'billing',
                    tourAnchor: 'nav-billing',
                },
            ]),
        },
        {
            label: 'Administración',
            items: filterNavItems([
                { to: '/app/settings', label: 'Configuración', icon: 'cog' },
                { to: '/app/audit', label: 'Auditoría', icon: 'shield', moduleId: 'audit', tourAnchor: 'nav-audit' },
                {
                    to: '/app/admin/users',
                    label: 'Usuarios',
                    icon: 'users',
                    moduleId: 'company_users',
                    tourAnchor: 'nav-company-users',
                },
            ]),
        },
    ];

    if (auth.user?.is_platform_admin) {
        groups.push({
            label: 'Plataforma',
            items: [
                {
                    to: '/app/platform/role-permissions',
                    label: 'Roles y permisos',
                    icon: 'shield',
                    tourAnchor: 'nav-platform-roles',
                },
            ],
        });
    }

    return groups.filter((group) => group.items.length > 0);
});

const navItemIndex = computed(() => {
    const map = new Map<string, number>();
    let i = 0;
    for (const group of navGroups.value) {
        for (const item of group.items) {
            map.set(item.to, i);
            i += 1;
        }
    }
    return map;
});

/** Activo el ítem del menú al estar en subrutas del mismo módulo (p. ej. tipos). */
function navItemActive(item: NavItem): boolean {
    return isActive(item.to, item.match ?? 'prefix');
}

function navDelay(to: string): string {
    const idx = navItemIndex.value.get(to) ?? 0;
    return `${80 + idx * 35}ms`;
}

function onDocumentClick(event: MouseEvent) {
    if (!avatarMenuOpen.value) {
        return;
    }
    const target = event.target as Node;
    if (avatarMenuRef.value && !avatarMenuRef.value.contains(target)) {
        avatarMenuOpen.value = false;
    }
}

function isActive(path: string, match: 'exact' | 'prefix' = 'prefix') {
    if (match === 'exact') {
        return route.path === path;
    }
    return route.path === path || route.path.startsWith(`${path}/`);
}

onMounted(async () => {
    document.addEventListener('click', onDocumentClick);
    const ok = await auth.ensureSession();
    if (!ok) {
        return;
    }
    company.hydrate(auth.companies);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
});

async function onCompanyChange(event: Event) {
    const id = Number((event.target as HTMLSelectElement).value);
    const found = auth.companies.find((c) => c.id === id);
    if (found) {
        company.select(found);
        await router.go(0);
    }
}

async function logout() {
    await auth.logout();
    company.clear();
    await router.push({ name: 'login' });
}

function openAvatarPicker() {
    avatarError.value = null;
    fileInput.value?.click();
}

async function onAvatarSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) {
        return;
    }
    avatarUploading.value = true;
    avatarError.value = null;
    try {
        await auth.uploadAvatar(file);
        avatarMenuOpen.value = false;
    } catch (e) {
        avatarError.value = (e as Error).message;
    } finally {
        avatarUploading.value = false;
    }
}
</script>

<template>
    <div class="app-portal-shell flex min-h-dvh overflow-hidden">
        <AppToastHost />
        <ProductTour />
        <BacteriumNetwork v-if="isDark" subdued warm />
        <AppAtmosphere v-if="isDark" />

        <aside
            class="glass-sidebar noah-sidebar-enter"
            data-tour="app-sidebar"
            :class="collapsed ? 'glass-sidebar-collapsed' : 'glass-sidebar-expanded'"
        >
            <div
                class="flex items-center gap-2 p-3"
                :class="collapsed ? 'flex-col justify-center' : 'justify-between'"
            >
                <NoahBrand v-if="!collapsed" show-wordmark />
                <NoahBrand v-else size="sm" />
                <button
                    type="button"
                    class="rounded-lg p-2 text-slate-300 hover:bg-white/10 hover:text-white"
                    :title="collapsed ? 'Expandir menú' : 'Colapsar menú'"
                    @click="toggle"
                >
                    <NavIcon :name="collapsed ? 'chevron-right' : 'chevron-left'" size="sm" />
                </button>
            </div>
            <nav class="flex-1 space-y-4 overflow-y-auto px-2 pb-4">
                <div v-for="group in navGroups" :key="group.label">
                    <p v-if="!collapsed" class="nav-section-label">{{ group.label }}</p>
                    <div v-else class="mb-2 h-px bg-white/10" aria-hidden="true" />
                    <div class="space-y-0.5">
                        <RouterLink
                            v-for="item in group.items"
                            :key="item.to"
                            :to="item.to"
                            class="nav-item noah-nav-enter"
                            :class="[
                                { 'nav-item-active': navItemActive(item) },
                                collapsed ? 'justify-center px-2.5' : '',
                            ]"
                            :style="{ animationDelay: navDelay(item.to) }"
                            :title="collapsed ? item.label : undefined"
                            :data-tour="item.tourAnchor"
                        >
                            <NavIcon :name="item.icon" class="nav-item-icon" />
                            <span class="nav-item-label">{{ item.label }}</span>
                        </RouterLink>
                    </div>
                </div>
            </nav>
            <p
                v-if="!collapsed"
                class="truncate px-4 pb-3 text-xs text-slate-400"
                :title="companyName"
            >
                {{ companyName }}
            </p>
        </aside>
        <div class="app-main-surface flex flex-col">
            <header class="portal-topbar" :class="isDark ? 'login-glass-premium' : 'glass-panel'">
                <div ref="avatarMenuRef" class="relative flex items-center gap-3">
                    <button
                        type="button"
                        class="flex items-center gap-3 rounded-xl py-1 pr-2 text-left hover:bg-white/5"
                        @click="avatarMenuOpen = !avatarMenuOpen"
                    >
                        <UserAvatar
                            :name="auth.user?.name ?? '?'"
                            :avatar-url="auth.user?.avatar_url"
                            size="md"
                        />
                        <div class="hidden sm:block">
                            <p class="portal-topbar__name text-sm font-medium">{{ auth.user?.name }}</p>
                            <p class="portal-topbar__email text-xs">{{ auth.user?.email }}</p>
                        </div>
                    </button>
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="hidden"
                        @change="onAvatarSelected"
                    />
                    <div
                        v-if="avatarMenuOpen"
                        class="login-glass-premium absolute left-0 top-full z-20 mt-2 w-56 p-3"
                    >
                        <p class="mb-2 text-xs font-medium text-slate-400">Foto de perfil</p>
                        <button
                            type="button"
                            class="login-cta w-full rounded-lg px-3 py-2 text-sm disabled:opacity-50"
                            :disabled="avatarUploading"
                            @click="openAvatarPicker"
                        >
                            {{ avatarUploading ? 'Subiendo…' : 'Elegir imagen' }}
                        </button>
                        <p v-if="avatarError" class="mt-2 text-xs text-red-400">{{ avatarError }}</p>
                        <p class="mt-2 text-[10px] text-slate-500">JPG, PNG o WebP · máx. 2 MB</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <select
                        v-if="auth.companies.length"
                        class="portal-topbar__company field-input max-w-[12rem]"
                        :value="company.current?.id"
                        @change="onCompanyChange"
                    >
                        <option v-for="c in auth.companies" :key="c.id" :value="c.id">
                            {{ c.name }}
                        </option>
                    </select>
                    <button
                        type="button"
                        class="portal-topbar__logout text-sm font-medium transition"
                        @click="logout"
                    >
                        Salir
                    </button>
                </div>
            </header>
            <main class="flex-1 overflow-y-auto p-4 sm:p-6">
                <RouterView v-slot="{ Component }">
                    <Transition name="noah-page" mode="out-in">
                        <component :is="Component" />
                    </Transition>
                </RouterView>
            </main>
        </div>
    </div>
</template>
