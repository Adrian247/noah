<script setup lang="ts">
withDefaults(
    defineProps<{
        variant?: 'info' | 'success' | 'warning' | 'danger';
    }>(),
    { variant: 'info' },
);

const styles = {
    info: 'border-white/12 bg-white/5 text-slate-200',
    success: 'border-emerald-500/25 bg-emerald-500/10 text-emerald-100',
    warning: 'border-amber-500/30 bg-amber-500/10 text-amber-100',
    danger: 'border-red-500/30 bg-red-500/10 text-red-100',
};
</script>

<template>
    <div class="rounded-xl border px-4 py-3 text-sm backdrop-blur-md" :class="styles[variant]">
        <slot />
    </div>
</template>
