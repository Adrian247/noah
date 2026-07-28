<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/api/client';
import { useCompanyStore } from '@/stores/company';
import { useProductTour } from '@/composables/useProductTour';
import GlassCard from '@/components/ui/GlassCard.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppModal from '@/components/ui/AppModal.vue';

const { hasCompleted, start } = useProductTour();
const showTourInvite = ref(false);

type Summary = {
    routines_pending_validation: number;
    routines_assigned: number;
    routines_validated: number;
    invoices_draft: number;
};

const company = useCompanyStore();
const summary = ref<Summary | null>(null);
const apiOk = ref(false);

function maybeOfferTour() {
    if (!company.current?.modules || hasCompleted()) {
        return;
    }
    showTourInvite.value = true;
}

watch(
    () => company.current?.modules,
    () => maybeOfferTour(),
    { immediate: true },
);

onMounted(async () => {
    try {
        const health = await fetch('/api/v1/health').then((r) => r.json());
        apiOk.value = health.status === 'ok';
    } catch {
        apiOk.value = false;
    }
    try {
        const res = await api<{ data: Summary }>('/dashboard/summary');
        summary.value = res.data;
    } catch {
        summary.value = null;
    }
});

const cards = [
    {
        key: 'pending',
        label: 'Pendientes de validación',
        value: () => summary.value?.routines_pending_validation,
        to: '/app/routines?status=pending_validation',
        accent: 'border-amber-500/25 bg-amber-500/10',
    },
    {
        key: 'assigned',
        label: 'Rutinas asignadas',
        value: () => summary.value?.routines_assigned,
        to: '/app/routines?status=assigned',
        accent: 'border-sky-500/25 bg-sky-500/10',
    },
    {
        key: 'validated',
        label: 'Validadas',
        value: () => summary.value?.routines_validated,
        to: '/app/routines?status=validated',
        accent: 'border-emerald-500/25 bg-emerald-500/10',
    },
    {
        key: 'invoices',
        label: 'Facturas borrador',
        value: () => summary.value?.invoices_draft,
        to: '/app/billing',
        accent: 'border-violet-500/25 bg-violet-500/10',
    },
];
</script>

<template>
    <div>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <PageHeader
                class="flex-1"
                :title="`Hola, ${company.current?.name ?? 'Noah'}`"
                subtitle="Accesos rápidos según tu rol. Valida rutinas, revisa facturación o continúa en campo."
            />
            <AppButton type="button" variant="secondary" class="shrink-0" @click="start(0)">
                Ver tour guiado
            </AppButton>
        </div>
        <div class="mb-4 flex gap-3">
            <GlassCard padding="sm" class="inline-flex items-center gap-2 text-sm">
                <span
                    class="h-2 w-2 rounded-full"
                    :class="apiOk ? 'bg-emerald-500' : 'bg-red-500'"
                />
                API {{ apiOk ? 'operativa' : 'no disponible' }}
            </GlassCard>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-tour="dashboard-cards">
            <RouterLink v-for="c in cards" :key="c.key" :to="c.to" class="block md-ripple-hover">
                <GlassCard hover :class="c.accent">
                    <p class="text-portal-muted text-sm font-medium">{{ c.label }}</p>
                    <p class="dashboard-stat-value mt-2 text-3xl font-semibold">{{ c.value() ?? '—' }}</p>
                </GlassCard>
            </RouterLink>
        </div>

        <AppModal
            :open="showTourInvite"
            title="Tour de Noah"
            size="sm"
            @close="showTourInvite = false"
        >
            <p class="text-portal-muted text-sm leading-relaxed">
                ¿Quieres un recorrido guiado con voz por el inicio, rutinas, catálogos y facturación? Solo se reproduce
                audio precargado; no se usa la API en cada visita.
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
