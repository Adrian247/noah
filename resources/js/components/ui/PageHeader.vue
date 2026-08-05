<script setup lang="ts">
defineProps<{
    title: string;
    subtitle?: string;
}>();
</script>

<template>
    <header class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            <h1 class="text-portal-heading text-2xl font-bold tracking-tight">{{ title }}</h1>
            <p v-if="subtitle" class="text-portal-muted mt-2 max-w-2xl text-sm leading-relaxed">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
