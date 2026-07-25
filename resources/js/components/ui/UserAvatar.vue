<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    name: string;
    avatarUrl?: string | null;
    size?: 'sm' | 'md' | 'lg';
}>();

const sizeClass = computed(() => {
    switch (props.size ?? 'md') {
        case 'sm':
            return 'h-8 w-8 text-xs';
        case 'lg':
            return 'h-14 w-14 text-lg';
        default:
            return 'h-10 w-10 text-sm';
    }
});

const initials = computed(() => {
    const parts = props.name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return props.name.slice(0, 2).toUpperCase();
});
</script>

<template>
    <div
        class="relative shrink-0 overflow-hidden rounded-full bg-primary-600 font-semibold text-white ring-2 ring-white/80"
        :class="sizeClass"
    >
        <img v-if="avatarUrl" :src="avatarUrl" :alt="name" class="h-full w-full object-cover" />
        <span v-else class="flex h-full w-full items-center justify-center">{{ initials }}</span>
    </div>
</template>
