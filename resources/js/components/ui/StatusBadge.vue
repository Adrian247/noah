<script setup lang="ts">
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';

const props = defineProps<{
    status: string;
}>();

const { isDark } = useTheme();

const mapDark: Record<string, string> = {
    assigned: 'bg-sky-500/20 text-sky-200 ring-1 ring-sky-500/30',
    pending_validation: 'bg-amber-500/20 text-amber-100 ring-1 ring-amber-500/35',
    pending_billing: 'bg-violet-500/20 text-violet-100 ring-1 ring-violet-500/30',
    validated: 'bg-emerald-500/20 text-emerald-100 ring-1 ring-emerald-500/30',
    invoiced: 'bg-violet-500/20 text-violet-100 ring-1 ring-violet-500/30',
    issued: 'bg-emerald-500/20 text-emerald-100 ring-1 ring-emerald-500/30',
    critical: 'bg-rose-500/25 text-rose-100 ring-1 ring-rose-500/40',
    high: 'bg-orange-500/20 text-orange-100 ring-1 ring-orange-500/35',
    medium: 'bg-amber-500/20 text-amber-100 ring-1 ring-amber-500/35',
    low: 'bg-emerald-500/20 text-emerald-100 ring-1 ring-emerald-500/30',
    active: 'bg-emerald-500/20 text-emerald-100 ring-1 ring-emerald-500/30',
    inactive: 'bg-white/10 text-slate-300 ring-1 ring-white/15',
};

const mapLight: Record<string, string> = {
    assigned: 'bg-sky-100 text-sky-950',
    pending_validation: 'bg-amber-100 text-amber-950',
    pending_billing: 'bg-violet-100 text-violet-950',
    validated: 'bg-emerald-100 text-emerald-950',
    invoiced: 'bg-violet-100 text-violet-950',
    issued: 'bg-emerald-100 text-emerald-950',
    critical: 'bg-rose-100 text-rose-950',
    high: 'bg-orange-100 text-orange-950',
    medium: 'bg-amber-100 text-amber-950',
    low: 'bg-emerald-100 text-emerald-950',
    active: 'bg-emerald-100 text-emerald-950',
    inactive: 'bg-slate-100 text-slate-700',
};

const portalStatusClass: Record<string, string> = {
    draft: 'portal-status-draft',
    published: 'portal-status-active',
};

const label: Record<string, string> = {
    assigned: 'Asignada',
    pending_validation: 'Pendiente validación',
    pending_billing: 'Pendiente facturación',
    validated: 'Validada',
    invoiced: 'Facturada',
    draft: 'Borrador',
    published: 'Publicado',
    issued: 'Emitida',
    critical: 'Crítico',
    high: 'Alto',
    medium: 'Medio',
    low: 'Bajo',
    active: 'Activo',
    inactive: 'Inactivo',
};

const classes = computed(() => {
    const portal = portalStatusClass[props.status];
    if (portal) {
        return portal;
    }
    const map = isDark.value ? mapDark : mapLight;
    return map[props.status] ?? (isDark.value ? 'bg-white/10 text-slate-300' : 'bg-slate-100 text-slate-700');
});
</script>

<template>
    <span
        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide"
        :class="classes"
    >
        {{ label[props.status] ?? status }}
    </span>
</template>
