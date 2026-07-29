<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        name: string;
        avatarUrl?: string | null;
        size?: 'sm' | 'md' | 'lg';
        /** Logos de cliente: evita recortar bordes con object-contain */
        imageFit?: 'cover' | 'contain';
    }>(),
    { size: 'md', imageFit: 'cover' },
);

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
        class="user-avatar relative shrink-0 overflow-hidden rounded-full bg-primary-600 font-semibold text-white"
        :class="sizeClass"
    >
        <div
            v-if="avatarUrl"
            class="user-avatar__media absolute inset-0 overflow-hidden rounded-full"
            :class="imageFit === 'contain' ? 'flex items-center justify-center bg-white/10 p-1' : ''"
        >
            <img
                :src="avatarUrl"
                :alt="name"
                class="user-avatar__img"
                :class="
                    imageFit === 'contain'
                        ? 'max-h-full max-w-full object-contain'
                        : 'h-full w-full object-cover'
                "
            />
        </div>
        <span v-else class="flex h-full w-full items-center justify-center">{{ initials }}</span>
    </div>
</template>
