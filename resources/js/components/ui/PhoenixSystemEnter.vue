<script setup lang="ts">
import { PHOENIX_LOGO_URL } from '@/lib/brandAssets';

withDefaults(
    defineProps<{
        /** Duración alineada con animación del menú (ms), solo referencia visual */
        durationMs?: number;
        message?: string;
    }>(),
    { durationMs: 2600, message: 'Entrando al sistema…' },
);
</script>

<template>
    <Teleport to="body">
        <div class="phoenix-system-enter" role="status" aria-live="polite" aria-busy="true">
            <div class="phoenix-system-enter__fx phoenix-system-enter__fx--play">
                <div class="phoenix-system-enter__beam" />
                <div class="phoenix-system-enter__glow" />
            </div>
            <div class="phoenix-system-enter__waves" aria-hidden="true">
                <div class="phoenix-system-enter__wave" />
                <div class="phoenix-system-enter__wave phoenix-system-enter__wave--delay-1" />
            </div>
            <img
                :src="PHOENIX_LOGO_URL"
                alt=""
                class="phoenix-system-enter__logo"
                width="160"
                height="160"
            />
            <p class="phoenix-system-enter__title">Phoenix</p>
            <p class="phoenix-system-enter__byline">Pyro Systems</p>
            <p class="phoenix-system-enter__status">{{ message }}</p>
        </div>
    </Teleport>
</template>
