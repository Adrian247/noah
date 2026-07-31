<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import { useCompanyStore } from '@/stores/company';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useProductTour } from '@/composables/useProductTour';
import { useToast } from '@/composables/useToast';
import { auditActionLabel } from '@/lib/auditLabels';
import GlassCard from '@/components/ui/GlassCard.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppModal from '@/components/ui/AppModal.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import NavIcon, { type NavIconName } from '@/components/ui/NavIcon.vue';
import DashboardStatCard from '@/components/dashboard/DashboardStatCard.vue';

const { hasCompleted, start } = useProductTour();
const toast = useToast();
const showTourInvite = ref(false);

type LowStockItem = {
    id: number;
    name: string;
    sku?: string | null;
    quantity_on_hand: number;
    min_stock: number;
    unit?: string | null;
};

type FocusRoutine = {
    id: number;
    status: string;
    scheduled_at?: string | null;
    asset_tag?: string | null;
    site_name?: string | null;
    routine_type_name?: string | null;
};

type ActivityItem = {
    id: number;
    action: string;
    occurred_at?: string | null;
    actor_name?: string | null;
};

type Summary = {
    routines_pending_validation: number;
    routines_assigned: number;
    routines_validated: number;
    invoices_draft: number;
    operations?: {
        routines_in_progress: number;
        routines_submitted: number;
        routines_pending_billing: number;
        routines_rejected: number;
        workflows_active: number;
        status_breakdown: Record<string, number>;
    };
    catalog?: {
        assets: number;
        sites: number;
        clients: number;
        suppliers: number;
        equipment_items: number;
        supply_items: number;
    };
    design?: {
        forms: number;
        reports: number;
        workflows: number;
        routine_types: number;
    };
    inventory?: {
        low_stock_count: number;
        low_stock_items: LowStockItem[];
    };
    focus_routines?: FocusRoutine[];
    recent_activity?: ActivityItem[];
    generated_at?: string | null;
};

type QuickLink = {
    to: string;
    label: string;
    description: string;
    icon: NavIconName;
    moduleId: string;
};

const auth = useAuthStore();
const company = useCompanyStore();
const { isVisible } = useModuleAccess();

const summary = ref<Summary | null>(null);
const apiOk = ref(false);
const loading = ref(true);
const loadError = ref(false);
const widgetLayout = ref<string[]>(['operations', 'catalog', 'inventory', 'design', 'activity']);
const DEFAULT_WIDGET_CATALOG = [
    { id: 'operations', label: 'Operaciones' },
    { id: 'catalog', label: 'Catálogo' },
    { id: 'inventory', label: 'Inventario' },
    { id: 'design', label: 'Diseño' },
    { id: 'activity', label: 'Actividad reciente' },
] as const;
const widgetCatalog = ref<{ id: string; label: string }[]>([...DEFAULT_WIDGET_CATALOG]);
const showWidgetModal = ref(false);
const widgetDraft = ref<string[]>([]);
const savingWidgets = ref(false);

const enabledWidgets = computed(() => new Set(widgetLayout.value));

function openWidgetModal() {
    widgetDraft.value = [...widgetLayout.value];
    showWidgetModal.value = true;
}

function toggleWidgetDraft(id: string) {
    const set = new Set(widgetDraft.value);
    if (set.has(id)) {
        set.delete(id);
    } else {
        set.add(id);
    }
    widgetDraft.value = [...set];
}

async function loadWidgetPreferences() {
    try {
        const res = await api<{ data: { layout: string[]; catalog: { id: string; label: string }[] } }>(
            '/dashboard/preferences',
        );
        if (res.data.layout?.length) {
            widgetLayout.value = res.data.layout;
        }
        if (res.data.catalog?.length) {
            widgetCatalog.value = res.data.catalog;
        }
    } catch {
        widgetCatalog.value = [...DEFAULT_WIDGET_CATALOG];
    }
}

async function loadDashboard() {
    loading.value = true;
    loadError.value = false;

    const healthPromise = fetch('/api/v1/health')
        .then((r) => r.json())
        .then((body) => {
            apiOk.value = body.status === 'ok';
        })
        .catch(() => {
            apiOk.value = false;
        });

    const summaryPromise = api<{ data: Summary }>('/dashboard/summary')
        .then((res) => {
            summary.value = res.data;
        })
        .catch(() => {
            summary.value = null;
            loadError.value = true;
        });

    const prefsPromise = loadWidgetPreferences();

    await Promise.all([healthPromise, summaryPromise, prefsPromise]);
    loading.value = false;
}

async function saveWidgetPreferences() {
    if (widgetDraft.value.length === 0) {
        toast.error('Selecciona al menos un widget.');
        return;
    }
    savingWidgets.value = true;
    try {
        await api('/dashboard/preferences', {
            method: 'PUT',
            body: JSON.stringify({ widgets: widgetDraft.value }),
        });
        widgetLayout.value = [...widgetDraft.value];
        showWidgetModal.value = false;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        savingWidgets.value = false;
    }
}

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) {
        return 'Buenos días';
    }
    if (hour < 19) {
        return 'Buenas tardes';
    }
    return 'Buenas noches';
});

const displayName = computed(() => auth.user?.name?.split(' ')[0] ?? 'equipo');

const roleLabel = computed(() => {
    const role = company.current?.role;
    const map: Record<string, string> = {
        administrator: 'Administrador',
        manager: 'Gestor',
        technician: 'Técnico',
        viewer: 'Solo lectura',
        client: 'Cliente',
    };
    return role ? (map[role] ?? role) : null;
});

const pipelineSegments = computed(() => {
    const b = summary.value?.operations?.status_breakdown ?? {};
    const items = [
        { key: 'assigned', label: 'Asignadas', tone: 'sky', count: b.assigned ?? 0 },
        { key: 'in_progress', label: 'En curso', tone: 'cyan', count: b.in_progress ?? 0 },
        { key: 'pending_validation', label: 'Validación', tone: 'amber', count: b.pending_validation ?? 0 },
        { key: 'validated', label: 'Validadas', tone: 'emerald', count: b.validated ?? 0 },
        { key: 'pending_billing', label: 'Facturación', tone: 'violet', count: b.pending_billing ?? 0 },
    ];
    const total = items.reduce((s, i) => s + i.count, 0) || 1;
    return items.map((i) => ({ ...i, pct: Math.round((i.count / total) * 100) }));
});

const pipelineTotal = computed(() =>
    pipelineSegments.value.reduce((s, i) => s + i.count, 0),
);

const quickLinks = computed(() => {
    const all: QuickLink[] = [
        {
            to: '/app/routines',
            label: 'Rutinas',
            description: 'Asignar, ejecutar y validar',
            icon: 'clipboard-list',
            moduleId: 'routines',
        },
        {
            to: '/app/inventory',
            label: 'Inventario',
            description: 'Stock e insumos',
            icon: 'archive',
            moduleId: 'inventory',
        },
        {
            to: '/app/assets',
            label: 'Activos',
            description: 'Equipos en campo',
            icon: 'cube',
            moduleId: 'assets',
        },
        {
            to: '/app/design/forms',
            label: 'Formularios',
            description: 'Diseño de captura',
            icon: 'document',
            moduleId: 'design_forms',
        },
        {
            to: '/app/design/workflows',
            label: 'Workflows',
            description: 'Automatización',
            icon: 'workflow',
            moduleId: 'design_workflows',
        },
        {
            to: '/app/billing',
            label: 'Facturación',
            description: 'Borradores y emisión',
            icon: 'receipt',
            moduleId: 'billing',
        },
        {
            to: '/app/audit',
            label: 'Auditoría',
            description: 'Trazabilidad',
            icon: 'shield',
            moduleId: 'audit',
        },
        {
            to: '/app/catalog/clients',
            label: 'Clientes',
            description: 'Cartera y portales',
            icon: 'building',
            moduleId: 'clients',
        },
    ];
    return all.filter((l) => isVisible(l.moduleId));
});

const catalogTiles = computed(() => {
    const c = summary.value?.catalog;
    if (!c) {
        return [];
    }
    return [
        { label: 'Activos', value: c.assets, to: '/app/assets', moduleId: 'assets', icon: 'cube' as NavIconName },
        { label: 'Sitios', value: c.sites, to: '/app/sites', moduleId: 'sites', icon: 'map-pin' as NavIconName },
        { label: 'Clientes', value: c.clients, to: '/app/catalog/clients', moduleId: 'clients', icon: 'building' as NavIconName },
        { label: 'Insumos', value: c.supply_items, to: '/app/inventory', moduleId: 'inventory', icon: 'archive' as NavIconName },
        { label: 'Equipos cat.', value: c.equipment_items, to: '/app/catalog/items', moduleId: 'catalog_items', icon: 'wrench' as NavIconName },
        { label: 'Proveedores', value: c.suppliers, to: '/app/catalog/suppliers', moduleId: 'catalog_suppliers', icon: 'truck' as NavIconName },
    ].filter((t) => isVisible(t.moduleId));
});

const designTiles = computed(() => {
    const d = summary.value?.design;
    if (!d) {
        return [];
    }
    return [
        { label: 'Formularios', value: d.forms, to: '/app/design/forms', moduleId: 'design_forms' },
        { label: 'Reportes', value: d.reports, to: '/app/design/reports', moduleId: 'design_reports' },
        { label: 'Workflows', value: d.workflows, to: '/app/design/workflows', moduleId: 'design_workflows' },
        { label: 'Tipos rutina', value: d.routine_types, to: '/app/routines/types', moduleId: 'design_routine_types' },
    ].filter((t) => isVisible(t.moduleId));
});

function formatRelativeTime(iso?: string | null): string {
    if (!iso) {
        return '';
    }
    const then = new Date(iso).getTime();
    const diff = Date.now() - then;
    const mins = Math.floor(diff / 60_000);
    if (mins < 1) {
        return 'Ahora';
    }
    if (mins < 60) {
        return `Hace ${mins} min`;
    }
    const hours = Math.floor(mins / 60);
    if (hours < 48) {
        return `Hace ${hours} h`;
    }
    return new Date(iso).toLocaleDateString('es-MX', { day: 'numeric', month: 'short' });
}

function formatScheduled(iso?: string | null): string {
    if (!iso) {
        return 'Sin fecha';
    }
    return new Date(iso).toLocaleString('es-MX', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function maybeOfferTour() {
    if (!company.current?.modules || hasCompleted()) {
        return;
    }
    showTourInvite.value = true;
}

watch(
    () => company.current?.id,
    (id) => {
        if (id != null) {
            void loadDashboard();
        }
    },
    { immediate: true },
);

watch(
    () => company.current?.modules,
    () => maybeOfferTour(),
    { immediate: true },
);

const primaryKpis = computed(() => [
    {
        key: 'pending',
        label: 'Pendientes de validación',
        value: summary.value?.routines_pending_validation ?? '—',
        to: '/app/validation',
        icon: 'shield' as NavIconName,
        tone: 'amber' as const,
        hint: 'Requieren tu revisión',
    },
    {
        key: 'assigned',
        label: 'Rutinas asignadas',
        value: summary.value?.routines_assigned ?? '—',
        to: '/app/routines?status=assigned',
        icon: 'clipboard-list' as NavIconName,
        tone: 'sky' as const,
        hint: 'En cola de ejecución',
    },
    {
        key: 'validated',
        label: 'Validadas',
        value: summary.value?.routines_validated ?? '—',
        to: '/app/routines?status=validated',
        icon: 'chart-bar' as NavIconName,
        tone: 'emerald' as const,
        hint: 'Listas para cierre',
    },
    {
        key: 'invoices',
        label: 'Facturas borrador',
        value: summary.value?.invoices_draft ?? '—',
        to: '/app/billing',
        icon: 'receipt' as NavIconName,
        tone: 'violet' as const,
        hint: 'Pendientes de emisión',
    },
]);
</script>

<template>
    <div class="dashboard-page">
        <header class="dashboard-hero phoenix-glass-card">
            <div class="dashboard-hero__content">
                <p class="dashboard-hero__eyebrow">{{ company.current?.name ?? 'Phoenix' }}</p>
                <h1 class="dashboard-hero__title">
                    {{ greeting }}, <span class="dashboard-hero__name">{{ displayName }}</span>
                </h1>
                <p class="dashboard-hero__subtitle">
                    Resumen de operación, catálogos y diseño en un solo vistazo.
                    <span v-if="roleLabel" class="dashboard-hero__role">{{ roleLabel }}</span>
                </p>
            </div>
            <div class="dashboard-hero__actions">
                <div class="dashboard-hero__status" :class="apiOk ? 'dashboard-hero__status--ok' : 'dashboard-hero__status--bad'">
                    <span class="dashboard-hero__status-dot" />
                    API {{ apiOk ? 'operativa' : 'sin conexión' }}
                </div>
                <AppButton type="button" variant="secondary" class="shrink-0" @click="openWidgetModal">
                    Personalizar
                </AppButton>
                <AppButton type="button" variant="secondary" class="shrink-0" @click="start(0)">
                    Tour guiado
                </AppButton>
            </div>
        </header>

        <div v-if="loadError" class="portal-callout portal-callout--warning">
            No pudimos cargar el resumen. Comprueba la empresa activa y vuelve a intentar.
            <button type="button" class="text-portal-link ml-2 underline" @click="loadDashboard">Reintentar</button>
        </div>

        <section class="dashboard-kpi-grid" data-tour="dashboard-cards" aria-label="Indicadores principales">
            <DashboardStatCard
                v-for="k in primaryKpis"
                :key="k.key"
                :label="k.label"
                :value="loading ? '…' : k.value"
                :to="k.to"
                :icon="k.icon"
                :tone="k.tone"
                :hint="k.hint"
            />
        </section>

        <div class="dashboard-main-grid">
            <GlassCard v-if="enabledWidgets.has('operations')" class="dashboard-panel dashboard-panel--pipeline" padding="lg">
                <div class="dashboard-panel__head">
                    <div>
                        <h2 class="dashboard-panel__title">Flujo de rutinas</h2>
                        <p class="dashboard-panel__desc">{{ pipelineTotal }} en el ciclo operativo</p>
                    </div>
                    <RouterLink to="/app/routines" class="dashboard-panel__link">Ver todas</RouterLink>
                </div>
                <div class="dashboard-pipeline-bar" role="img" :aria-label="`Distribución de ${pipelineTotal} rutinas`">
                    <div
                        v-for="seg in pipelineSegments"
                        :key="seg.key"
                        class="dashboard-pipeline-bar__seg"
                        :class="`dashboard-pipeline-bar__seg--${seg.tone}`"
                        :style="{ flexGrow: Math.max(seg.count, seg.count > 0 ? 1 : 0) }"
                        :title="`${seg.label}: ${seg.count}`"
                    />
                </div>
                <ul class="dashboard-pipeline-legend">
                    <li v-for="seg in pipelineSegments" :key="seg.key">
                        <span class="dashboard-pipeline-legend__dot" :class="`dashboard-pipeline-legend__dot--${seg.tone}`" />
                        <span class="dashboard-pipeline-legend__label">{{ seg.label }}</span>
                        <span class="dashboard-pipeline-legend__count">{{ seg.count }}</span>
                    </li>
                </ul>
                <div v-if="summary?.operations" class="dashboard-mini-metrics">
                    <div v-if="isVisible('routines')">
                        <span class="dashboard-mini-metrics__val">{{ summary.operations.workflows_active }}</span>
                        <span class="dashboard-mini-metrics__lbl">Workflows activos</span>
                    </div>
                    <div v-if="isVisible('routines')">
                        <span class="dashboard-mini-metrics__val">{{ summary.operations.routines_in_progress }}</span>
                        <span class="dashboard-mini-metrics__lbl">En curso</span>
                    </div>
                    <div v-if="isVisible('billing')">
                        <span class="dashboard-mini-metrics__val">{{ summary.operations.routines_pending_billing }}</span>
                        <span class="dashboard-mini-metrics__lbl">Pend. facturación</span>
                    </div>
                </div>
            </GlassCard>

            <GlassCard v-if="quickLinks.length" class="dashboard-panel" padding="lg">
                <h2 class="dashboard-panel__title">Accesos rápidos</h2>
                <p class="dashboard-panel__desc mb-4">Módulos habilitados para tu rol</p>
                <div class="dashboard-quick-grid">
                    <RouterLink
                        v-for="link in quickLinks"
                        :key="link.to"
                        :to="link.to"
                        class="dashboard-quick-link md-ripple-hover"
                    >
                        <span class="dashboard-quick-link__icon">
                            <NavIcon :name="link.icon" size="sm" />
                        </span>
                        <span class="dashboard-quick-link__text">
                            <span class="dashboard-quick-link__label">{{ link.label }}</span>
                            <span class="dashboard-quick-link__desc">{{ link.description }}</span>
                        </span>
                    </RouterLink>
                </div>
            </GlassCard>
        </div>

        <div class="dashboard-secondary-grid">
            <GlassCard v-if="enabledWidgets.has('operations') && isVisible('routines') && (summary?.focus_routines?.length ?? 0) > 0" padding="lg" class="dashboard-panel">
                <div class="dashboard-panel__head">
                    <div>
                        <h2 class="dashboard-panel__title">Prioridad operativa</h2>
                        <p class="dashboard-panel__desc">Validación y ejecución pendiente</p>
                    </div>
                </div>
                <ul class="dashboard-focus-list">
                    <li v-for="r in summary?.focus_routines" :key="r.id">
                        <RouterLink :to="`/app/routines/${r.id}`" class="dashboard-focus-item">
                            <div class="dashboard-focus-item__main">
                                <span class="dashboard-focus-item__title">
                                    {{ r.routine_type_name ?? 'Rutina' }}
                                    <span v-if="r.asset_tag" class="text-portal-muted font-normal">· {{ r.asset_tag }}</span>
                                </span>
                                <span class="dashboard-focus-item__meta">
                                    {{ r.site_name ?? 'Sin sitio' }} · {{ formatScheduled(r.scheduled_at) }}
                                </span>
                            </div>
                            <StatusBadge :status="r.status" />
                        </RouterLink>
                    </li>
                </ul>
            </GlassCard>

            <GlassCard
                v-if="enabledWidgets.has('inventory') && isVisible('inventory') && (summary?.inventory?.low_stock_count ?? 0) > 0"
                padding="lg"
                class="dashboard-panel dashboard-panel--alert"
            >
                <div class="dashboard-panel__head">
                    <div>
                        <h2 class="dashboard-panel__title">Stock bajo</h2>
                        <p class="dashboard-panel__desc">
                            {{ summary?.inventory?.low_stock_count }} artículo(s) bajo
                        </p>
                    </div>
                    <RouterLink to="/app/inventory" class="dashboard-panel__link">Inventario</RouterLink>
                </div>
                <ul class="dashboard-stock-list">
                    <li v-for="item in summary?.inventory?.low_stock_items" :key="item.id">
                        <span class="dashboard-stock-list__name">{{ item.name }}</span>
                        <span class="dashboard-stock-list__qty">
                            {{ item.quantity_on_hand }}
                            <span class="text-portal-muted">/ {{ item.min_stock }} {{ item.unit ?? 'u' }}</span>
                        </span>
                    </li>
                </ul>
            </GlassCard>

            <GlassCard v-if="enabledWidgets.has('catalog') && catalogTiles.length" padding="lg" class="dashboard-panel">
                <h2 class="dashboard-panel__title">Catálogo</h2>
                <p class="dashboard-panel__desc mb-4">Registros maestros de la empresa</p>
                <div class="dashboard-catalog-grid">
                    <RouterLink
                        v-for="tile in catalogTiles"
                        :key="tile.label"
                        :to="tile.to"
                        class="dashboard-catalog-tile md-ripple-hover"
                    >
                        <NavIcon :name="tile.icon" size="sm" />
                        <span class="dashboard-catalog-tile__val">{{ loading ? '…' : tile.value }}</span>
                        <span class="dashboard-catalog-tile__lbl">{{ tile.label }}</span>
                    </RouterLink>
                </div>
            </GlassCard>

            <GlassCard v-if="enabledWidgets.has('design') && designTiles.length" padding="lg" class="dashboard-panel">
                <h2 class="dashboard-panel__title">Estudio de diseño</h2>
                <p class="dashboard-panel__desc mb-4">Formularios, reportes y automatización</p>
                <div class="dashboard-design-grid">
                    <RouterLink
                        v-for="tile in designTiles"
                        :key="tile.label"
                        :to="tile.to"
                        class="dashboard-design-tile md-ripple-hover"
                    >
                        <span class="dashboard-design-tile__val">{{ loading ? '…' : tile.value }}</span>
                        <span class="dashboard-design-tile__lbl">{{ tile.label }}</span>
                    </RouterLink>
                </div>
            </GlassCard>

            <GlassCard
                v-if="enabledWidgets.has('activity') && isVisible('audit') && (summary?.recent_activity?.length ?? 0) > 0"
                padding="lg"
                class="dashboard-panel dashboard-panel--activity"
            >
                <div class="dashboard-panel__head">
                    <div>
                        <h2 class="dashboard-panel__title">Actividad reciente</h2>
                        <p class="dashboard-panel__desc">Últimos eventos auditados</p>
                    </div>
                    <RouterLink to="/app/audit" class="dashboard-panel__link">Auditoría</RouterLink>
                </div>
                <ul class="dashboard-activity-feed">
                    <li v-for="ev in summary?.recent_activity" :key="ev.id" class="dashboard-activity-item">
                        <span class="dashboard-activity-item__dot" />
                        <div class="dashboard-activity-item__body">
                            <p class="dashboard-activity-item__action">{{ auditActionLabel(ev.action) }}</p>
                            <p class="dashboard-activity-item__meta">
                                {{ ev.actor_name ?? 'Sistema' }} · {{ formatRelativeTime(ev.occurred_at) }}
                            </p>
                        </div>
                    </li>
                </ul>
            </GlassCard>
        </div>

        <p v-if="summary?.generated_at" class="dashboard-footer-meta text-portal-muted text-xs">
            Actualizado {{ formatRelativeTime(summary.generated_at) }}
        </p>

        <AppModal :open="showWidgetModal" title="Widgets del inicio" size="sm" @close="showWidgetModal = false">
            <p class="text-portal-muted mb-3 text-sm">Elige qué paneles mostrar en tu dashboard.</p>
            <div class="space-y-2">
                <label
                    v-for="w in widgetCatalog"
                    :key="w.id"
                    class="text-portal-muted flex items-center gap-2 text-sm"
                >
                    <input
                        type="checkbox"
                        :checked="widgetDraft.includes(w.id)"
                        @change="toggleWidgetDraft(w.id)"
                    />
                    {{ w.label }}
                </label>
            </div>
            <template #footer>
                <AppButton type="button" variant="ghost" @click="showWidgetModal = false">Cancelar</AppButton>
                <AppButton type="button" :disabled="savingWidgets" @click="saveWidgetPreferences">
                    {{ savingWidgets ? 'Guardando…' : 'Guardar' }}
                </AppButton>
            </template>
        </AppModal>

        <AppModal :open="showTourInvite" title="Tour de Phoenix" size="sm" @close="showTourInvite = false">
            <p class="text-portal-muted text-sm leading-relaxed">
                ¿Quieres un recorrido guiado por el inicio, rutinas, catálogos y facturación?
            </p>
            <template #footer>
                <button
                    type="button"
                    class="text-portal-muted rounded-xl px-4 py-2 text-sm hover:bg-white/5"
                    @click="showTourInvite = false"
                >
                    Ahora no
                </button>
                <AppButton
                    type="button"
                    @click="
                        showTourInvite = false;
                        start(0);
                    "
                >
                    Iniciar tour
                </AppButton>
            </template>
        </AppModal>
    </div>
</template>
