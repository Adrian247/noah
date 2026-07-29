<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { RouterLink, RouterView, useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useCompanyStore } from '@/stores/company';
import { useSidebarCollapsed } from '@/composables/useSidebarCollapsed';
import { usePermissions } from '@/composables/usePermissions';
import { useModuleAccess } from '@/composables/useModuleAccess';
import UserAvatar from '@/components/ui/UserAvatar.vue';
import NavIcon, { type NavIconName } from '@/components/ui/NavIcon.vue';
import PhoenixBrand from '@/components/ui/PhoenixBrand.vue';
import BacteriumNetwork from '@/components/BacteriumNetwork.vue';
import AppAtmosphere from '@/components/AppAtmosphere.vue';
import PortalLightFrost from '@/components/PortalLightFrost.vue';
import AppToastHost from '@/components/ui/AppToastHost.vue';
import ProductTour from '@/components/onboarding/ProductTour.vue';
import { useTheme } from '@/composables/useTheme';
import SidebarNavTooltip from '@/components/layout/SidebarNavTooltip.vue';
import { placeFloatingPanel } from '@/lib/floatingUi';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';

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
const toast = useToast();
const route = useRoute();
const { collapsed, animating: sidebarAnimating, phase: sidebarPhase, toggle } = useSidebarCollapsed();
const avatarMenuOpen = ref(false);
const avatarUploading = ref(false);
const avatarError = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const avatarMenuRef = ref<HTMLElement | null>(null);
const sessionTriggerRef = ref<HTMLButtonElement | null>(null);
const sessionMenuRef = ref<HTMLElement | null>(null);
const sessionMenuStyle = ref<{ top: string; left: string }>({
    top: '0px',
    left: '0px',
});

const { isDark } = useTheme();
const companyName = computed(() => company.current?.name ?? 'Empresa');

function filterNavItems(items: NavItem[]): NavItem[] {
    return items.filter((item) => {
        if (item.moduleId === 'design_workflows' && !auth.user?.is_platform_admin) {
            return false;
        }
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
                { to: '/app/inventory', label: 'Inventario', icon: 'archive', moduleId: 'inventory', tourAnchor: 'nav-inventory' },
                { to: '/app/assets', label: 'Activos', icon: 'cube', moduleId: 'assets', tourAnchor: 'nav-assets' },
            ]),
        },
        {
            label: 'Catálogos',
            items: filterNavItems([
                { to: '/app/catalog/items', label: 'Equipos', icon: 'wrench', moduleId: 'catalog_items', tourAnchor: 'nav-catalog-items' },
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
                    to: '/app/platform/tenants',
                    label: 'Clientes de plataforma',
                    icon: 'building',
                    tourAnchor: 'nav-platform-tenants',
                },
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

/** Activo el ítem del menú al estar en subrutas del mismo módulo (p. ej. tipos). */
function navItemActive(item: NavItem): boolean {
    return isActive(item.to, item.match ?? 'prefix');
}

function onDocumentClick(event: MouseEvent) {
    if (!avatarMenuOpen.value) {
        return;
    }
    const target = event.target as Node;
    const menu = document.getElementById('sidebar-session-menu');
    if (
        avatarMenuRef.value?.contains(target) ||
        (menu && menu.contains(target))
    ) {
        return;
    }
    avatarMenuOpen.value = false;
}

function updateSessionMenuPosition() {
    const el = sessionTriggerRef.value;
    const menu = sessionMenuRef.value;
    if (!el || !menu) {
        return;
    }
    const rect = el.getBoundingClientRect();
    const prefer = 'right';
    const { top, left } = placeFloatingPanel(rect, menu.offsetWidth, menu.offsetHeight, {
        prefer,
    });
    sessionMenuStyle.value = {
        top: `${top}px`,
        left: `${left}px`,
    };
}

function toggleSessionMenu() {
    avatarMenuOpen.value = !avatarMenuOpen.value;
}

watch(avatarMenuOpen, async (open) => {
    if (!open) {
        return;
    }
    await nextTick();
    requestAnimationFrame(() => updateSessionMenuPosition());
});

function onSessionMenuReposition() {
    if (avatarMenuOpen.value) {
        updateSessionMenuPosition();
    }
}

watch(collapsed, () => {
    avatarMenuOpen.value = false;
});

function isActive(path: string, match: 'exact' | 'prefix' = 'prefix') {
    if (match === 'exact') {
        return route.path === path;
    }
    return route.path === path || route.path.startsWith(`${path}/`);
}

onMounted(async () => {
    document.addEventListener('click', onDocumentClick);
    window.addEventListener('resize', onSessionMenuReposition);
    const ok = await auth.ensureSession();
    if (!ok) {
        return;
    }
    company.hydrate(auth.companies);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    window.removeEventListener('resize', onSessionMenuReposition);
});

async function onCompanyChange(event: Event) {
    const select = event.target as HTMLSelectElement;
    const previousId = company.current?.id;
    const id = Number(select.value);
    const found = auth.companies.find((c) => c.id === id);
    if (!found) {
        return;
    }
    if (found.assumed && auth.user?.is_platform_admin) {
        try {
            await api(`/platform/tenants/${found.id}/assume`, { method: 'POST' });
        } catch (e) {
            toast.error((e as Error).message);
            if (previousId !== undefined) {
                select.value = String(previousId);
            }
            return;
        }
    }
    company.select(found);
    await router.go(0);
}

function companyOptionLabel(c: { name: string; assumed?: boolean; company_is_active?: boolean }): string {
    let label = c.name;
    if (c.assumed) {
        label += ' (plataforma)';
    }
    if (c.company_is_active === false) {
        label += ' — inactivo';
    }
    return label;
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
    <div
        class="app-portal-shell"
        :class="[
            collapsed ? 'app-portal-shell--sidebar-collapsed' : 'app-portal-shell--sidebar-expanded',
            sidebarAnimating ? 'app-portal-shell--sidebar-animating' : '',
            sidebarPhase === 'collapsing' ? 'app-portal-shell--sidebar-collapsing' : '',
            sidebarPhase === 'expanding' ? 'app-portal-shell--sidebar-expanding' : '',
        ]"
    >
        <AppToastHost />
        <ProductTour />
        <BacteriumNetwork subdued warm :light="!isDark" />
        <AppAtmosphere />
        <PortalLightFrost v-if="!isDark" />

        <aside
            class="glass-sidebar phoenix-sidebar-enter"
            data-tour="app-sidebar"
            :class="[
                collapsed ? 'glass-sidebar-collapsed' : 'glass-sidebar-expanded',
                sidebarAnimating ? 'glass-sidebar--animating' : '',
                sidebarPhase === 'collapsing' ? 'glass-sidebar--collapsing' : '',
                sidebarPhase === 'expanding' ? 'glass-sidebar--expanding' : '',
            ]"
        >
            <div
                v-if="sidebarAnimating"
                class="sidebar-collapse-fx"
                aria-hidden="true"
                :class="
                    sidebarPhase === 'collapsing'
                        ? 'sidebar-collapse-fx--collapse'
                        : 'sidebar-collapse-fx--expand'
                "
            >
                <div class="sidebar-collapse-fx__beam" />
                <div class="sidebar-collapse-fx__glow" />
                <div class="sidebar-collapse-fx__scan" />
            </div>
            <div class="sidebar-header">
                <PhoenixBrand
                    :class="collapsed ? 'shrink-0' : 'min-w-0 flex-1'"
                    :size="collapsed ? 'sm' : 'lg'"
                    :show-wordmark="!collapsed"
                    :animated="!sidebarAnimating"
                />
                <SidebarNavTooltip
                    :label="collapsed ? 'Expandir menú' : 'Colapsar menú'"
                    :enabled="collapsed"
                >
                    <button
                        type="button"
                        class="sidebar-toggle-btn rounded-lg text-slate-300 transition-colors hover:bg-white/10 hover:text-white"
                        :class="{ 'sidebar-toggle-btn--spin': sidebarAnimating }"
                        @click="toggle"
                    >
                        <NavIcon :name="collapsed ? 'chevron-right' : 'chevron-left'" size="sm" />
                    </button>
                </SidebarNavTooltip>
            </div>
            <nav class="flex-1 space-y-4 overflow-x-hidden overflow-y-auto px-2 pb-4">
                <div v-for="group in navGroups" :key="group.label">
                    <p
                        class="nav-section-label"
                        :class="{ 'nav-section-label--collapsed': collapsed }"
                    >
                        {{ group.label }}
                    </p>
                    <div class="space-y-0.5">
                        <SidebarNavTooltip
                            v-for="item in group.items"
                            :key="item.to"
                            :label="item.label"
                            :enabled="collapsed"
                        >
                            <RouterLink
                                :to="item.to"
                                class="nav-item"
                                :class="[
                                    { 'nav-item-active': navItemActive(item) },
                                    collapsed ? 'justify-center px-2.5' : '',
                                ]"
                                :data-tour="item.tourAnchor"
                            >
                                <NavIcon :name="item.icon" class="nav-item-icon" />
                                <span class="nav-item-label">{{ item.label }}</span>
                            </RouterLink>
                        </SidebarNavTooltip>
                    </div>
                </div>
            </nav>
            <div
                class="sidebar-footer border-t border-white/10 px-3 py-3"
                :class="{ 'sidebar-footer--collapsed': collapsed }"
            >
                <div ref="avatarMenuRef" class="sidebar-session relative mb-3">
                    <SidebarNavTooltip :label="auth.user?.name ?? 'Tu cuenta'" :enabled="collapsed">
                        <button
                            ref="sessionTriggerRef"
                            type="button"
                            class="sidebar-session__trigger flex w-full items-center gap-2.5 rounded-xl py-2 text-left hover:bg-white/5"
                            :class="collapsed ? 'justify-center px-0' : 'px-2'"
                            @click="toggleSessionMenu"
                        >
                            <UserAvatar
                                :name="auth.user?.name ?? '?'"
                                :avatar-url="auth.user?.avatar_url"
                                size="md"
                            />
                            <div v-if="!collapsed" class="min-w-0 flex-1">
                                <p class="sidebar-session__name truncate text-sm font-medium text-slate-100">
                                    {{ auth.user?.name }}
                                </p>
                                <p class="sidebar-session__email truncate text-[11px] text-slate-400">
                                    {{ auth.user?.email }}
                                </p>
                            </div>
                        </button>
                    </SidebarNavTooltip>
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="hidden"
                        @change="onAvatarSelected"
                    />
                </div>
                <Teleport to="body">
                    <div
                        v-if="avatarMenuOpen"
                        id="sidebar-session-menu"
                        ref="sessionMenuRef"
                        class="login-glass-premium sidebar-session-menu p-3"
                        :style="sessionMenuStyle"
                    >
                        <p class="sidebar-session-menu__heading">Tu cuenta</p>
                        <button
                            type="button"
                            class="login-cta mb-2 w-full rounded-lg px-3 py-2 text-sm disabled:opacity-50"
                            :disabled="avatarUploading"
                            @click="openAvatarPicker"
                        >
                            {{ avatarUploading ? 'Subiendo…' : 'Cambiar foto' }}
                        </button>
                        <RouterLink
                            v-if="auth.user?.is_platform_admin"
                            to="/app/platform/tenants"
                            class="sidebar-session-menu__link"
                            @click="avatarMenuOpen = false"
                        >
                            Gestionar clientes de plataforma
                        </RouterLink>
                        <button
                            type="button"
                            class="sidebar-session-menu__logout w-full rounded-lg px-3 py-2 text-left text-sm font-medium"
                            @click="logout"
                        >
                            Cerrar sesión
                        </button>
                        <p v-if="avatarError" class="mt-2 text-xs text-red-400">{{ avatarError }}</p>
                        <p class="mt-2 text-[10px] text-slate-500">JPG, PNG o WebP · máx. 2 MB</p>
                    </div>
                </Teleport>
                <label v-if="auth.companies.length > 1 && !collapsed" class="sidebar-workspace-label" data-tour="sidebar-workspace">
                    <span class="sidebar-workspace-label__text">Empresa activa</span>
                    <select
                        class="sidebar-workspace-select field-input w-full text-xs"
                        :value="company.current?.id"
                        @change="onCompanyChange"
                    >
                        <option v-for="c in auth.companies" :key="c.id" :value="c.id">
                            {{ companyOptionLabel(c) }}
                        </option>
                    </select>
                </label>
                <p
                    v-else-if="!collapsed"
                    class="sidebar-company-footer truncate text-xs text-slate-400"
                    :title="companyName"
                >
                    {{ companyName }}
                </p>
            </div>
        </aside>
        <div class="app-main-surface flex flex-col">
            <main class="app-main-scroll flex-1 overflow-y-auto overflow-x-hidden p-4 sm:p-6">
                <RouterView v-slot="{ Component }">
                    <Transition name="phoenix-page" mode="out-in">
                        <component :is="Component" class="phoenix-page-content" />
                    </Transition>
                </RouterView>
            </main>
        </div>
    </div>
</template>
