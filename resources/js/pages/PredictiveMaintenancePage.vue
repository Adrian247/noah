<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppButton from '@/components/ui/AppButton.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import ConfigurableDataTable from '@/components/ui/ConfigurableDataTable.vue';
import type { TableColumnDef } from '@/lib/tableColumns';

type Site = { id: number; name: string };

type Driver = {
    code: string;
    label: string;
    factor: number;
    contribution: number;
    evidence: string;
};

type FailureModeShare = {
    failure_mode_id: number;
    code: string;
    name: string;
    system: string;
    share: number;
    probability: number;
    expected_failures: number;
};

type Prediction = {
    asset_id: number;
    tag: string;
    name: string;
    equipment_class: string | null;
    probability: number;
    expected_failures: number;
    risk_level: 'critical' | 'high' | 'medium' | 'low';
    drivers: Driver[];
    failure_modes: FailureModeShare[];
    top_failure_mode?: { code: string; name: string; failure_mode_id: number } | null;
};

type PredictionsPayload = {
    as_of: string;
    horizon_days: number;
    evaluated_assets: number;
    returned_assets: number;
    model: {
        kind: string;
        version: string;
        algorithm_semver?: string | null;
        feature_source?: string;
    };
    risk_summary: Record<string, number>;
    predictions: Prediction[];
    notes?: string[];
    data_coverage?: {
        latest_validated_routine: string | null;
        assets_with_routines: number;
        feature_source: string;
    };
};

type HealthPayload = {
    asset: {
        id: number;
        tag: string;
        name: string;
        equipment_class: string | null;
    };
    as_of: string;
    reliability: Record<string, number | string | null>;
    prediction: Prediction & Record<string, unknown>;
    recent_failures: Array<Record<string, unknown>>;
    pending_work_orders: Array<Record<string, unknown>>;
};

type FailureModeRow = { id: number; code: string; name: string };

const CLASS_OPTIONS = [
    { value: '', label: 'Toda la flota' },
    { value: 'SCOOPTRAM', label: 'Scooptram / LHD' },
    { value: 'CAMION_BAJO_PERFIL', label: 'Camión de bajo perfil' },
    { value: 'JUMBO', label: 'Jumbo / barrenación' },
    { value: 'QUEBRADORA', label: 'Quebradora' },
    { value: 'MOLINO', label: 'Molino' },
    { value: 'FILTRO', label: 'Filtro' },
];

const HORIZON_OPTIONS = [
    { value: '7', label: 'Próximos 7 días' },
    { value: '14', label: 'Próximos 14 días' },
    { value: '30', label: 'Próximos 30 días' },
];

const RISK_HELP: Record<string, string> = {
    critical: 'Atiende ya: se espera al menos una falla en la ventana.',
    high: 'Prioriza esta semana: riesgo elevado.',
    medium: 'Vigila: señales de deterioro.',
    low: 'Sin urgencia relativa.',
};

const route = useRoute();
const router = useRouter();
const toast = useToast();

const showAdvanced = ref(false);
const hasPredicted = ref(false);
const viewMode = ref<'assets' | 'clients' | 'inventory'>('assets');
const loading = ref(false);
const demandLoading = ref(false);
const inventoryLoading = ref(false);
const sites = ref<Site[]>([]);
const payload = ref<PredictionsPayload | null>(null);
const demandPayload = ref<{
    as_of: string;
    horizon_days: number;
    predictions: Array<{
        client_id: number;
        client_name: string;
        routine_type_name: string;
        service_line_label: string;
        expected_requests: number;
        probability: number;
        score: number;
        days_since_last: number;
        drivers: Array<{ code: string; label: string; evidence: string }>;
    }>;
    notes?: string[];
} | null>(null);
const inventoryPayload = ref<{
    as_of: string;
    horizon_days: number;
    predictions: Array<{
        client_id: number;
        client_name: string;
        catalog_item_code: string;
        item_name: string;
        expected_requests: number;
        probability: number;
        score: number;
        drivers: Array<{ code: string; label: string; evidence: string }>;
    }>;
    notes?: string[];
} | null>(null);
const demandLine = ref('manufacturing');
const health = ref<HealthPayload | null>(null);
const healthLoading = ref(false);
const selectedAssetId = ref<number | null>(null);
const failureModes = ref<FailureModeRow[]>([]);

const siteId = ref('');
const equipmentClass = ref('');
const riskFilter = ref('');
const horizonDays = ref('14');
const failureMode = ref('');
const asOf = ref('');

const siteOptions = computed(() => [
    { value: '', label: 'Todos los sitios' },
    ...sites.value.map((s) => ({ value: String(s.id), label: s.name })),
]);

const failureModeOptions = computed(() => [
    { value: '', label: 'Cualquier tipo de falla' },
    ...failureModes.value.map((m) => ({ value: m.code, label: m.name })),
]);

const riskSummary = computed(() => payload.value?.risk_summary ?? {});
const urgentCount = computed(() => {
    const s = riskSummary.value;
    return (s.critical ?? 0) + (s.high ?? 0);
});

const filteredPredictions = computed(() => {
    const rows = payload.value?.predictions ?? [];
    if (!riskFilter.value) return rows;
    return rows.filter((p) => p.risk_level === riskFilter.value);
});

const predictionColumns = computed((): TableColumnDef[] => [
    { id: 'tag', label: 'Equipo' },
    { id: 'risk', label: 'Prioridad' },
    { id: 'mode', label: 'Modo probable' },
    { id: 'why', label: 'Por qué' },
    { id: 'chance', label: 'Chance (equipo)' },
]);

function fmtPct(value: number | null | undefined, digits = 0): string {
    if (value === null || value === undefined || Number.isNaN(value)) return '—';
    return `${(value * 100).toFixed(digits)}%`;
}

function fmtNum(value: number | null | undefined, digits = 2): string {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return '—';
    return Number(value).toFixed(digits);
}

function topMode(row: Prediction): string {
    return row.top_failure_mode?.name ?? row.failure_modes?.[0]?.name ?? 'Sin modo dominante';
}

function topDriver(row: Prediction): string {
    return row.drivers?.[0]?.label ?? 'Historial de servicios del equipo';
}

function riskPlain(level: string): string {
    return RISK_HELP[level] ?? '';
}

async function loadSites() {
    try {
        sites.value = (await api<{ data: Site[] }>('/sites')).data;
    } catch {
        sites.value = [];
    }
}

async function loadFailureModeOptions() {
    try {
        failureModes.value = (await api<{ data: FailureModeRow[] }>('/predictive/failure-modes')).data;
    } catch {
        failureModes.value = [];
    }
}

async function predictFleet() {
    loading.value = true;
    health.value = null;
    selectedAssetId.value = null;
    try {
        const qs = new URLSearchParams({
            horizon_days: horizonDays.value,
            limit: '100',
            persist: '1',
        });
        if (siteId.value) qs.set('site_id', siteId.value);
        if (equipmentClass.value) qs.set('equipment_class', equipmentClass.value);
        if (failureMode.value) qs.set('failure_mode', failureMode.value);
        const explicitAsOf = asOf.value.trim();
        if (explicitAsOf) qs.set('as_of', explicitAsOf);

        const res = await api<{ data: PredictionsPayload }>(`/predictive/predictions?${qs}`);
        payload.value = res.data;
        hasPredicted.value = true;
        if (res.data.evaluated_assets === 0) {
            toast.warning(res.data.notes?.[0] ?? 'No hay activos con servicios aplicados para evaluar.');
        }
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function predictDemand() {
    demandLoading.value = true;
    try {
        const qs = new URLSearchParams({
            horizon_days: horizonDays.value,
            limit: '50',
        });
        if (demandLine.value) qs.set('service_line', demandLine.value);
        const explicitAsOf = asOf.value.trim();
        if (explicitAsOf) qs.set('as_of', explicitAsOf);
        const res = await api<{ data: NonNullable<typeof demandPayload.value> }>(
            `/predictive/client-demand?${qs}`,
        );
        demandPayload.value = res.data;
        if ((res.data.predictions?.length ?? 0) === 0) {
            toast.warning(res.data.notes?.[0] ?? 'Sin demanda estimable en el periodo.');
        }
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        demandLoading.value = false;
    }
}

async function predictInventory() {
    inventoryLoading.value = true;
    try {
        const qs = new URLSearchParams({
            horizon_days: horizonDays.value,
            limit: '50',
        });
        const explicitAsOf = asOf.value.trim();
        if (explicitAsOf) qs.set('as_of', explicitAsOf);
        const res = await api<{ data: NonNullable<typeof inventoryPayload.value> }>(
            `/predictive/inventory-demand?${qs}`,
        );
        inventoryPayload.value = res.data;
        if ((res.data.predictions?.length ?? 0) === 0) {
            toast.warning(res.data.notes?.[0] ?? 'Sin demanda de inventario estimable.');
        }
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        inventoryLoading.value = false;
    }
}

async function openHealth(assetId: number) {
    healthLoading.value = true;
    selectedAssetId.value = assetId;
    health.value = null;
    try {
        const qs = asOf.value.trim() ? `?as_of=${encodeURIComponent(asOf.value.trim())}` : '';
        health.value = (await api<{ data: HealthPayload }>(`/predictive/assets/${assetId}/health${qs}`)).data;
        await router.replace({ name: 'predictive', query: { ...route.query, asset: String(assetId) } });
    } catch (e) {
        toast.error((e as Error).message);
        selectedAssetId.value = null;
    } finally {
        healthLoading.value = false;
    }
}

function closeHealth() {
    health.value = null;
    selectedAssetId.value = null;
    const query = { ...route.query };
    delete query.asset;
    void router.replace({ name: 'predictive', query });
}

const demandColumns = computed((): TableColumnDef[] => [
    { id: 'client', label: 'Cliente' },
    { id: 'type', label: 'Tipo de servicio' },
    { id: 'line', label: 'Categoría' },
    { id: 'expected', label: 'Esperado' },
    { id: 'chance', label: 'Probabilidad' },
    { id: 'why', label: 'Por qué' },
]);

const inventoryColumns = computed((): TableColumnDef[] => [
    { id: 'client', label: 'Cliente' },
    { id: 'item', label: 'Artículo' },
    { id: 'expected', label: 'Esperado' },
    { id: 'chance', label: 'Probabilidad' },
    { id: 'why', label: 'Por qué' },
]);

onMounted(async () => {
    await Promise.all([loadSites(), loadFailureModeOptions()]);
    await predictFleet();
    const assetQuery = route.query.asset;
    if (typeof assetQuery === 'string' && assetQuery) {
        await openHealth(Number(assetQuery));
    }
});
</script>

<template>
    <div class="portal-page" data-tour="predictive">
        <PageHeader
            title="Predictivo"
            subtitle="Riesgo en equipos (mantenimiento), demanda de manufactura e inventario (solicitud de artículos)."
        />

        <div class="mb-4 flex flex-wrap gap-2">
            <button
                type="button"
                class="filter-chip"
                :class="{ 'filter-chip--active': viewMode === 'assets' }"
                @click="viewMode = 'assets'"
            >
                Mantenimiento
            </button>
            <button
                type="button"
                class="filter-chip"
                :class="{ 'filter-chip--active': viewMode === 'clients' }"
                @click="
                    viewMode = 'clients';
                    if (!demandPayload) void predictDemand();
                "
            >
                Manufactura
            </button>
            <button
                type="button"
                class="filter-chip"
                :class="{ 'filter-chip--active': viewMode === 'inventory' }"
                @click="
                    viewMode = 'inventory';
                    if (!inventoryPayload) void predictInventory();
                "
            >
                Inventario
            </button>
        </div>

        <section
            v-if="viewMode === 'clients'"
            class="mb-5 rounded-2xl border border-[color:var(--portal-border)] bg-[color:var(--portal-surface)] p-4 sm:p-5"
        >
            <p class="text-portal-muted mb-4 text-sm leading-relaxed">
                Ranking de clientes según historial de servicios de manufactura o instalación.
            </p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto] lg:items-end">
                <MaterialSelect
                    v-model="horizonDays"
                    label="Horizonte"
                    :options="HORIZON_OPTIONS"
                />
                <MaterialSelect
                    v-model="demandLine"
                    label="Categoría"
                    :options="[
                        { value: 'manufacturing', label: 'Manufactura' },
                        { value: 'installation', label: 'Instalación' },
                        { value: '', label: 'Ambas' },
                    ]"
                />
                <AppButton type="button" class="w-full lg:w-auto" :disabled="demandLoading" @click="predictDemand">
                    {{ demandLoading ? 'Calculando…' : 'Predecir demanda' }}
                </AppButton>
            </div>
            <ConfigurableDataTable
                v-if="demandPayload"
                class="mt-4"
                table-id="predictive-client-demand"
                :columns="demandColumns"
                :rows="demandPayload.predictions"
                row-key="client_id"
                empty-text="Sin predicciones de demanda."
            >
                <template #client="{ row }">
                    <span class="font-medium text-portal-heading">{{ (row as any).client_name }}</span>
                </template>
                <template #type="{ row }">{{ (row as any).routine_type_name }}</template>
                <template #line="{ row }">{{ (row as any).service_line_label }}</template>
                <template #expected="{ row }">{{ Number((row as any).expected_requests).toFixed(2) }}</template>
                <template #chance="{ row }">{{ fmtPct(Number((row as any).probability)) }}</template>
                <template #why="{ row }">
                    <span class="text-portal-muted text-sm">
                        {{ (row as any).drivers?.[0]?.evidence ?? '—' }}
                    </span>
                </template>
            </ConfigurableDataTable>
        </section>

        <section
            v-if="viewMode === 'inventory'"
            class="mb-5 rounded-2xl border border-[color:var(--portal-border)] bg-[color:var(--portal-surface)] p-4 sm:p-5"
        >
            <p class="text-portal-muted mb-4 text-sm leading-relaxed">
                Probabilidad de que un cliente final solicite compra de artículos del catálogo (demanda de inventario).
            </p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_auto] lg:items-end">
                <MaterialSelect
                    v-model="horizonDays"
                    label="Horizonte"
                    :options="HORIZON_OPTIONS"
                />
                <AppButton
                    type="button"
                    class="w-full lg:w-auto"
                    :disabled="inventoryLoading"
                    @click="predictInventory"
                >
                    {{ inventoryLoading ? 'Calculando…' : 'Predecir inventario' }}
                </AppButton>
            </div>
            <ConfigurableDataTable
                v-if="inventoryPayload"
                class="mt-4"
                table-id="predictive-inventory-demand"
                :columns="inventoryColumns"
                :rows="inventoryPayload.predictions"
                :row-key="(row) => `${(row as any).client_id}-${(row as any).catalog_item_code}`"
                empty-text="Sin predicciones de inventario."
            >
                <template #client="{ row }">
                    <span class="font-medium text-portal-heading">{{ (row as any).client_name }}</span>
                </template>
                <template #item="{ row }">
                    <span class="text-portal-heading">{{ (row as any).item_name }}</span>
                    <p class="text-portal-muted font-mono text-[11px]">{{ (row as any).catalog_item_code }}</p>
                </template>
                <template #expected="{ row }">{{ Number((row as any).expected_requests).toFixed(2) }}</template>
                <template #chance="{ row }">{{ fmtPct(Number((row as any).probability)) }}</template>
                <template #why="{ row }">
                    <span class="text-portal-muted text-sm">
                        {{ (row as any).drivers?.[0]?.evidence ?? '—' }}
                    </span>
                </template>
            </ConfigurableDataTable>
        </section>

        <template v-if="viewMode === 'assets'">
        <section
            class="mb-5 rounded-2xl border border-[color:var(--portal-border)] bg-[color:var(--portal-surface)] p-4 sm:p-5"
        >
            <p class="text-portal-muted mb-3 text-sm leading-relaxed">
                Estima el riesgo de falla del <strong class="text-portal-heading">equipo completo</strong> en el
                horizonte y prioriza un <strong class="text-portal-heading">modo de falla</strong> (sistema o tipo,
                p.&nbsp;ej. hidráulico o motor). No predice qué pieza o componente concreto fallará; los componentes
                solo pueden aparecer como evidencia que sube el riesgo.
            </p>
            <ol class="text-portal-muted mb-4 list-decimal space-y-1 pl-5 text-sm leading-relaxed">
                <li>Elige el horizonte (cuántos días adelante mirar).</li>
                <li>Pulsa <strong class="text-portal-heading">Predecir flota</strong> (o filtra por clase/equipo).</li>
                <li>Haz clic en un activo para ver la evidencia basada en sus servicios.</li>
            </ol>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto] lg:items-end">
                <MaterialSelect v-model="horizonDays" label="Horizonte" :options="HORIZON_OPTIONS" />
                <MaterialSelect v-model="equipmentClass" label="Clase de equipo" :options="CLASS_OPTIONS" />
                <AppButton type="button" class="w-full lg:w-auto" :disabled="loading" @click="predictFleet">
                    {{ loading ? 'Prediciendo…' : 'Predecir flota' }}
                </AppButton>
            </div>

            <button
                type="button"
                class="text-portal-muted mt-3 text-sm underline-offset-2 hover:underline"
                @click="showAdvanced = !showAdvanced"
            >
                {{ showAdvanced ? 'Ocultar filtros' : 'Más filtros (sitio, tipo de falla, fecha)' }}
            </button>

            <div v-if="showAdvanced" class="mt-3 grid gap-3 md:grid-cols-3">
                <MaterialSelect v-model="siteId" label="Sitio" :options="siteOptions" />
                <MaterialSelect v-model="failureMode" label="Tipo de falla" :options="failureModeOptions" />
                <MaterialField v-model="asOf" label="Fecha de corte (opcional)" type="date" />
            </div>
        </section>

        <template v-if="hasPredicted && payload">
            <div
                v-if="payload.evaluated_assets === 0"
                class="mb-4 rounded-2xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm leading-relaxed"
            >
                <p class="font-medium text-portal-heading">No hay historial de servicios para predecir</p>
                <p class="text-portal-muted mt-1">
                    {{ payload.notes?.[0] }}
                </p>
            </div>

            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-portal-heading text-lg font-semibold">
                        Resultado · próximos {{ payload.horizon_days }} días
                    </h2>
                    <p class="text-portal-muted mt-1 text-sm">
                        {{ payload.evaluated_assets }} activos con servicios
                        <template v-if="urgentCount > 0">
                            · <strong class="text-portal-heading">{{ urgentCount }}</strong> prioritarios
                        </template>
                        · corte {{ payload.as_of }}
                        <template v-if="payload.model.algorithm_semver">
                            · algoritmo v{{ payload.model.algorithm_semver }}
                        </template>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="level in ['critical', 'high', 'medium', 'low']"
                        :key="level"
                        type="button"
                        class="filter-chip"
                        :class="{ 'filter-chip--active': riskFilter === level }"
                        @click="riskFilter = riskFilter === level ? '' : level"
                    >
                        <StatusBadge :status="level" />
                        <span class="ml-1">{{ riskSummary[level] ?? 0 }}</span>
                    </button>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(20rem,0.8fr)]">
                <ConfigurableDataTable
                    table-id="predictive-predictions"
                    :columns="predictionColumns"
                    :rows="filteredPredictions"
                    row-key="asset_id"
                    clickable
                    empty-text="Ningún activo coincide con el filtro."
                    @row-click="(row) => openHealth((row as Prediction).asset_id)"
                >
                    <template #tag="{ row }">
                        <div>
                            <p class="font-medium text-portal-heading">{{ (row as Prediction).tag }}</p>
                            <p class="text-portal-muted text-xs">
                                {{ (row as Prediction).equipment_class ?? '—' }} · {{ (row as Prediction).name }}
                            </p>
                        </div>
                    </template>
                    <template #risk="{ row }">
                        <StatusBadge :status="(row as Prediction).risk_level" />
                    </template>
                    <template #mode="{ row }">{{ topMode(row as Prediction) }}</template>
                    <template #why="{ row }">
                        <span class="text-portal-muted text-sm">{{ topDriver(row as Prediction) }}</span>
                    </template>
                    <template #chance="{ row }">{{ fmtPct((row as Prediction).probability) }}</template>
                </ConfigurableDataTable>

                <aside class="rounded-2xl border border-[color:var(--portal-border)] bg-[color:var(--portal-surface)] p-4">
                    <div v-if="healthLoading" class="text-portal-muted py-8 text-center text-sm">Cargando…</div>
                    <div v-else-if="!health" class="text-portal-muted py-6 text-sm leading-relaxed">
                        <p class="text-portal-heading mb-2 font-medium">Detalle del activo</p>
                        <p>
                            Selecciona un equipo. Verás el riesgo del activo, el modo de falla más probable y la
                            evidencia del historial de servicios (frecuencia, atrasos, consumos, comentarios). No
                            indica una pieza concreta.
                        </p>
                    </div>
                    <div v-else class="space-y-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h3 class="text-portal-heading text-base font-semibold">{{ health.asset.tag }}</h3>
                                <p class="text-portal-muted text-xs">{{ health.asset.name }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <StatusBadge
                                    v-if="health.prediction?.risk_level"
                                    :status="String(health.prediction.risk_level)"
                                />
                                <button type="button" class="text-portal-muted text-xs underline" @click="closeHealth">
                                    Cerrar
                                </button>
                            </div>
                        </div>
                        <p class="text-sm text-portal-heading">
                            {{ riskPlain(String(health.prediction?.risk_level ?? '')) }}
                            Chance de falla del equipo:
                            <strong>{{ fmtPct(Number(health.prediction?.probability)) }}</strong>
                            (E={{ fmtNum(Number(health.prediction?.expected_failures)) }}).
                        </p>
                        <p
                            v-if="health.prediction?.top_failure_mode?.name || health.prediction?.failure_modes?.[0]?.name"
                            class="text-portal-muted text-xs leading-relaxed"
                        >
                            Modo más probable:
                            <span class="text-portal-heading">{{ topMode(health.prediction) }}</span>
                            (sistema o tipo de falla, no componente).
                        </p>
                        <section>
                            <h4 class="text-portal-muted mb-2 text-xs font-semibold uppercase">Por qué</h4>
                            <ul class="space-y-2">
                                <li
                                    v-for="driver in (health.prediction?.drivers as Driver[] | undefined) ?? []"
                                    :key="driver.code"
                                    class="rounded-lg border border-[color:var(--portal-border)] px-3 py-2"
                                >
                                    <p class="text-sm font-medium text-portal-heading">{{ driver.label }}</p>
                                    <p class="text-portal-muted mt-1 text-xs">{{ driver.evidence }}</p>
                                </li>
                                <li
                                    v-if="!((health.prediction?.drivers as Driver[] | undefined)?.length)"
                                    class="text-portal-muted text-xs"
                                >
                                    Sin factores por encima de la línea base.
                                </li>
                            </ul>
                        </section>
                    </div>
                </aside>
            </div>
        </template>
        </template>
    </div>
</template>
