<script setup lang="ts">
import { ref } from 'vue';
import { PHOENIX_LOGO_URL } from '@/lib/brandAssets';

withDefaults(
    defineProps<{
        size?: 'sm' | 'md' | 'lg';
        showWordmark?: boolean;
        variant?: 'sidebar' | 'light';
        animated?: boolean;
    }>(),
    { size: 'md', showWordmark: false, variant: 'sidebar', animated: false },
);

const box = { sm: 'h-10 w-10', md: 'h-11 w-11', lg: 'h-[3.25rem] w-[3.25rem]' };
const title = { sm: 'text-base', md: 'text-lg', lg: 'text-xl' };
const logoSrc = ref(PHOENIX_LOGO_URL);

function onLogoError() {
    logoSrc.value = '/images/phoenix-logo.png?v=3';
}
</script>

<template>
    <div
        class="phoenix-brand flex items-center gap-2.5"
        :class="{ 'phoenix-brand--animated': animated }"
    >
        <img
            :src="logoSrc"
            alt="Phoenix"
            class="phoenix-brand__logo block shrink-0 object-contain drop-shadow-[0_0_14px_rgba(251,146,60,0.55)]"
            :class="box[size]"
            width="56"
            height="56"
            decoding="async"
            @error="onLogoError"
        />
        <div v-if="showWordmark" class="min-w-0 text-left">
            <p
                class="font-bold tracking-tight"
                :class="[title[size], variant === 'light' ? 'text-slate-900' : 'text-white']"
            >
                Phoenix
            </p>
            <p
                class="text-[10px] font-semibold uppercase tracking-[0.14em]"
                :class="variant === 'light' ? 'text-orange-700/80' : 'text-orange-300/85'"
            >
                Pyro Systems
            </p>
        </div>
    </div>
</template>
