<script setup lang="ts">
withDefaults(
    defineProps<{
        variant?: 'info' | 'success' | 'warning' | 'danger';
    }>(),
    { variant: 'info' },
);

const styles = {
    info: 'portal-callout portal-callout--info',
    success: 'portal-callout portal-callout--success',
    warning: 'portal-callout portal-callout--warning',
    danger: 'portal-callout portal-callout--danger',
};
</script>

<template>
    <div :class="styles[variant]">
        <slot />
    </div>
</template>
