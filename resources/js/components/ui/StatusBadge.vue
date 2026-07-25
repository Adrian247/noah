<script setup lang="ts">
const props = defineProps<{
    status: string;
}>();

const map: Record<string, string> = {
    assigned: 'bg-sky-100 text-sky-900',
    pending_validation: 'bg-amber-100 text-amber-950',
    validated: 'bg-emerald-100 text-emerald-900',
    invoiced: 'bg-violet-100 text-violet-900',
    draft: 'bg-slate-200 text-slate-800',
    issued: 'bg-emerald-100 text-emerald-900',
};

const label: Record<string, string> = {
    assigned: 'Asignada',
    pending_validation: 'Pendiente validación',
    validated: 'Validada',
    invoiced: 'Facturada',
    draft: 'Borrador',
    issued: 'Emitida',
};
</script>

<template>
    <span
        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide"
        :class="map[props.status] ?? 'bg-slate-100 text-slate-700'"
    >
        {{ label[props.status] ?? status }}
    </span>
</template>
