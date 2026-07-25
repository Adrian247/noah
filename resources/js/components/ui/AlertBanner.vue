<script setup lang="ts">
withDefaults(
    defineProps<{
        variant?: 'info' | 'success' | 'warning' | 'danger';
    }>(),
    { variant: 'info' },
);

const styles = {
    info: 'border-slate-200/80 bg-white/80 text-slate-800',
    success: 'border-emerald-200 bg-emerald-50/90 text-emerald-950',
    warning: 'border-amber-200 bg-amber-50/90 text-amber-950',
    danger: 'border-red-200 bg-red-50/90 text-red-950',
};
</script>

<template>
    <div class="rounded-xl border px-4 py-3 text-sm backdrop-blur-sm" :class="styles[variant]">
        <slot />
    </div>
</template>
