<script setup lang="ts">
defineProps<{
    title: string;
    description: string;
}>();
</script>

<template>
    <div class="client-portal-empty">
        <div class="client-portal-empty__icon" aria-hidden="true">
            <svg viewBox="0 0 48 48" fill="none" class="h-12 w-12">
                <circle cx="24" cy="24" r="22" stroke="currentColor" stroke-opacity="0.25" stroke-width="2" />
                <path
                    d="M16 28h16M20 20h8"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                />
            </svg>
        </div>
        <p class="text-portal-heading text-base font-semibold">{{ title }}</p>
        <p class="text-portal-muted mt-2 max-w-sm text-sm leading-relaxed">{{ description }}</p>
    </div>
</template>
