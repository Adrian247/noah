<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/api/client';
import { useCompanyStore } from '@/stores/company';
import GlassCard from '@/components/ui/GlassCard.vue';
import PageHeader from '@/components/ui/PageHeader.vue';

type Summary = {
    routines_pending_validation: number;
    routines_assigned: number;
    routines_validated: number;
    invoices_draft: number;
};

const company = useCompanyStore();
const summary = ref<Summary | null>(null);
const apiOk = ref(false);

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
        <PageHeader
            :title="`Hola, ${company.current?.name ?? 'Noah'}`"
            subtitle="Accesos rápidos según tu rol. Valida rutinas, revisa facturación o continúa en campo."
        />
        <div class="mb-4 flex gap-3">
            <GlassCard padding="sm" class="inline-flex items-center gap-2 text-sm">
                <span
                    class="h-2 w-2 rounded-full"
                    :class="apiOk ? 'bg-emerald-500' : 'bg-red-500'"
                />
                API {{ apiOk ? 'operativa' : 'no disponible' }}
            </GlassCard>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <RouterLink v-for="c in cards" :key="c.key" :to="c.to" class="block md-ripple-hover">
                <GlassCard hover :class="c.accent">
                    <p class="text-portal-muted text-sm font-medium">{{ c.label }}</p>
                    <p class="dashboard-stat-value mt-2 text-3xl font-semibold">{{ c.value() ?? '—' }}</p>
                </GlassCard>
            </RouterLink>
        </div>
    </div>
</template>
