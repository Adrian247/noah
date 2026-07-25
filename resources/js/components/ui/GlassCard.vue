<script setup lang="ts">
withDefaults(
    defineProps<{
        padding?: 'sm' | 'md' | 'lg';
        hover?: boolean;
    }>(),
    { padding: 'md', hover: false },
);

const pad = {
    sm: 'p-3',
    md: 'p-5',
    lg: 'p-6',
};
</script>

<template>
    <div
        class="rounded-2xl border border-slate-200/90 bg-white shadow-[var(--shadow-glass)]"
        :class="[pad[padding], hover ? 'md-ripple-hover hover:bg-white/80' : '']"
    >
        <slot />
    </div>
</template>
