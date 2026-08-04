<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import GlassCard from '@/components/ui/GlassCard.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import ClientPortalEmptyState from '@/components/portal/ClientPortalEmptyState.vue';

type RoutineRow = {
    id: number;
    status: string;
    asset?: { tag: string; serial_number?: string | null };
    routine_type?: { name: string };
    invoice?: { id: number; status: string } | null;
};

const toast = useToast();
const items = ref<RoutineRow[]>([]);
const loading = ref(true);

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: RoutineRow[] }>('/portal/routines');
        const payload = res as { data?: RoutineRow[] };
        items.value = Array.isArray(payload.data) ? payload.data : [];
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="client-portal-page">
        <PageHeader
            title="Servicios en tus equipos"
            subtitle="Historial de servicios de mantenimiento e inspección en activos vinculados a tu cuenta. Abre cada servicio para ver trazabilidad, informes y facturación."
        />

        <GlassCard v-if="loading" padding="lg">
            <p class="text-portal-muted animate-pulse text-sm">Cargando servicios…</p>
        </GlassCard>

        <div v-else-if="items.length" class="client-portal-routine-grid">
            <RouterLink
                v-for="r in items"
                :key="r.id"
                :to="`/portal/routines/${r.id}`"
                class="client-portal-routine-card md-ripple-hover"
            >
                <GlassCard padding="lg" hover class="h-full">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <span class="client-portal-id-badge">Servicio #{{ r.id }}</span>
                            <p class="text-portal-heading mt-2 text-lg font-semibold tracking-tight">
                                {{ r.routine_type?.name ?? 'Servicio técnico' }}
                            </p>
                            <p class="text-portal-muted mt-1 text-sm">
                                Activo <strong class="text-portal-heading">{{ r.asset?.tag ?? '—' }}</strong>
                                <span v-if="r.asset?.serial_number">
                                    · Serie {{ r.asset.serial_number }}
                                </span>
                            </p>
                        </div>
                        <StatusBadge :status="r.status" />
                    </div>
                    <p
                        v-if="r.invoice"
                        class="text-portal-muted mt-4 text-xs"
                    >
                        Factura disponible en portal
                    </p>
                    <p class="text-portal-link mt-4 text-sm font-medium">Ver detalle y documentos →</p>
                </GlassCard>
            </RouterLink>
        </div>

        <GlassCard v-else padding="lg">
            <ClientPortalEmptyState
                title="Sin servicios visibles"
                description="No hay servicios asociados a equipos de tu organización. Si esperabas ver historial, confirma con tu proveedor la asignación por número de serie."
            />
        </GlassCard>
    </div>
</template>
