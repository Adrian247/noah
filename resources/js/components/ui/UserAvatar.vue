<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
    avatarToneFromName,
    averageImageLuminance,
    logoBackdropForLuminance,
} from '@/lib/avatarTone';

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

const imgRef = ref<HTMLImageElement | null>(null);
const logoBackdrop = ref<string>('var(--portal-avatar-logo-bg-neutral)');

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

const initialsTone = computed(() => avatarToneFromName(props.name));

const shellStyle = computed(() => {
    if (props.avatarUrl) {
        return { background: logoBackdrop.value };
    }

    return {
        background: initialsTone.value.background,
        color: initialsTone.value.color,
    };
});

const mediaStyle = computed(() => ({
    background: logoBackdrop.value,
}));

function analyzeLogoContrast(img: HTMLImageElement) {
    logoBackdrop.value = logoBackdropForLuminance(averageImageLuminance(img));
}

function onImageLoad(event: Event) {
    const img = event.target as HTMLImageElement | null;
    if (img) {
        analyzeLogoContrast(img);
    }
}

function resetLogoBackdrop() {
    logoBackdrop.value = 'var(--portal-avatar-logo-bg-neutral)';
}

watch(
    () => props.avatarUrl,
    () => {
        resetLogoBackdrop();
        const img = imgRef.value;
        if (img?.complete && img.naturalWidth > 0) {
            analyzeLogoContrast(img);
        }
    },
);
</script>

<template>
    <div
        class="user-avatar relative shrink-0 overflow-hidden rounded-full font-semibold"
        :class="sizeClass"
        :style="shellStyle"
    >
        <div
            v-if="avatarUrl"
            class="user-avatar__media absolute inset-0 overflow-hidden rounded-full"
            :class="imageFit === 'contain' ? 'flex items-center justify-center p-1' : ''"
            :style="mediaStyle"
        >
            <img
                ref="imgRef"
                :src="avatarUrl"
                :alt="name"
                class="user-avatar__img"
                :class="
                    imageFit === 'contain'
                        ? 'max-h-full max-w-full object-contain'
                        : 'h-full w-full object-cover'
                "
                @load="onImageLoad"
            />
        </div>
        <span v-else class="flex h-full w-full items-center justify-center">{{ initials }}</span>
    </div>
</template>
