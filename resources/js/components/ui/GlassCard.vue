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
        class="noah-glass-card rounded-2xl"
        :class="[pad[padding], hover ? 'noah-glass-card--hover md-ripple-hover' : '']"
    >
        <slot />
    </div>
</template>
