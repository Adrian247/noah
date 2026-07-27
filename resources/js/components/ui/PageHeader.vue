<script setup lang="ts">
defineProps<{
    title: string;
    subtitle?: string;
}>();
</script>

<template>
    <header class="mb-6">
        <h1 class="text-portal-heading text-2xl font-bold tracking-tight">{{ title }}</h1>
        <p v-if="subtitle" class="text-portal-muted mt-2 max-w-2xl text-sm leading-relaxed">{{ subtitle }}</p>
    </header>
</template>
