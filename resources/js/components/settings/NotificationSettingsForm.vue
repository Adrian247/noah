<script setup lang="ts">
import { storeToRefs } from 'pinia';
import AppButton from '@/components/ui/AppButton.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';
import { useToast } from '@/composables/useToast';
import {
    AUTO_CLOSE_DURATION_DEFAULT_SEC,
    AUTO_CLOSE_DURATION_MAX_SEC,
    AUTO_CLOSE_DURATION_MIN_SEC,
    AUTO_CLOSE_DURATION_STEP_SEC,
    NOTIFICATION_POSITIONS,
    type ToastPosition,
} from '@/lib/notificationPreferences';
import { useNotificationPreferencesStore } from '@/stores/notificationPreferences';
import type { ToastVariant } from '@/stores/toast';

const prefs = useNotificationPreferencesStore();
const { position, soundEnabled, autoCloseEnabled, autoCloseSeconds } = storeToRefs(prefs);
const toast = useToast();

const positionOptions = NOTIFICATION_POSITIONS.map((p) => ({
    value: p.id,
    label: p.label,
}));

function onPositionChange(value: string | number | null) {
    if (typeof value === 'string' && NOTIFICATION_POSITIONS.some((p) => p.id === value)) {
        prefs.setPosition(value as ToastPosition);
    }
}

function onDurationInput(event: Event) {
    const value = Number((event.target as HTMLInputElement).value);
    prefs.setAutoCloseSeconds(value);
}

function sendTest(variant: ToastVariant) {
    const labels: Record<ToastVariant, string> = {
        success: 'Notificación de éxito de prueba.',
        danger: 'Notificación de error de prueba.',
        warning: 'Notificación de advertencia de prueba.',
        info: 'Notificación informativa de prueba.',
    };
    toast.push(labels[variant], variant);
}
</script>

<template>
    <div class="space-y-5">
        <MaterialSelect
            label="Posición en pantalla"
            :model-value="position"
            :options="positionOptions"
            @update:model-value="onPositionChange"
        />

        <label class="flex cursor-pointer items-start gap-3">
            <input
                v-model="autoCloseEnabled"
                type="checkbox"
                class="mt-1 h-4 w-4 rounded border-white/20 bg-transparent accent-amber-500"
            />
            <span>
                <span class="text-portal-heading block text-sm font-medium">Cerrar automáticamente</span>
                <span class="text-portal-muted mt-0.5 block text-xs leading-relaxed">
                    Si está desactivado, las notificaciones permanecen hasta que las cierres manualmente y se
                    apilan en pantalla.
                </span>
            </span>
        </label>

        <div v-if="autoCloseEnabled" class="notification-auto-close space-y-2 pl-7">
            <div class="flex items-baseline justify-between gap-3">
                <p class="text-portal-heading text-sm font-medium">Tiempo visible</p>
                <span class="text-portal-muted shrink-0 text-sm tabular-nums">{{ autoCloseSeconds }} s</span>
            </div>
            <input
                class="notification-auto-close__range w-full"
                type="range"
                :min="AUTO_CLOSE_DURATION_MIN_SEC"
                :max="AUTO_CLOSE_DURATION_MAX_SEC"
                :step="AUTO_CLOSE_DURATION_STEP_SEC"
                :value="autoCloseSeconds"
                :aria-valuemin="AUTO_CLOSE_DURATION_MIN_SEC"
                :aria-valuemax="AUTO_CLOSE_DURATION_MAX_SEC"
                :aria-valuenow="autoCloseSeconds"
                aria-label="Segundos antes de cerrar la notificación"
                @input="onDurationInput"
            />
            <div class="text-portal-muted flex justify-between text-[0.65rem] tabular-nums">
                <span>{{ AUTO_CLOSE_DURATION_MIN_SEC }} s</span>
                <span>{{ AUTO_CLOSE_DURATION_MAX_SEC }} s</span>
            </div>
            <p class="text-portal-muted text-xs leading-relaxed">
                Por defecto {{ AUTO_CLOSE_DURATION_DEFAULT_SEC }} s. Recomendado 4–8 s para avisos breves; hasta
                {{ AUTO_CLOSE_DURATION_MAX_SEC }} s si el mensaje es largo.
            </p>
        </div>

        <label class="flex cursor-pointer items-start gap-3">
            <input
                v-model="soundEnabled"
                type="checkbox"
                class="mt-1 h-4 w-4 rounded border-white/20 bg-transparent accent-amber-500"
            />
            <span>
                <span class="text-portal-heading block text-sm font-medium">Sonido al mostrar notificación</span>
                <span class="text-portal-muted mt-0.5 block text-xs leading-relaxed">
                    Reproduce un efecto breve según el tipo (éxito, error, aviso o información).
                </span>
            </span>
        </label>

        <div class="space-y-2">
            <p class="text-portal-heading text-sm font-medium">Notificación de prueba</p>
            <p class="text-portal-muted text-xs">
                Dispara un toast con la posición, cierre y sonido actuales.
            </p>
            <div class="flex flex-wrap gap-2">
                <AppButton type="button" variant="secondary" @click="sendTest('success')">Éxito</AppButton>
                <AppButton type="button" variant="secondary" @click="sendTest('danger')">Error</AppButton>
                <AppButton type="button" variant="secondary" @click="sendTest('warning')">Aviso</AppButton>
                <AppButton type="button" variant="secondary" @click="sendTest('info')">Info</AppButton>
            </div>
        </div>
    </div>
</template>

<style scoped>
.notification-auto-close__range {
    -webkit-appearance: none;
    appearance: none;
    height: 0.35rem;
    border-radius: 999px;
    background: color-mix(in srgb, var(--portal-border) 55%, transparent);
    accent-color: rgb(245 158 11);
    cursor: pointer;
}

.notification-auto-close__range:focus-visible {
    outline: 2px solid color-mix(in srgb, rgb(245 158 11) 55%, transparent);
    outline-offset: 2px;
}

.notification-auto-close__range::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 1rem;
    height: 1rem;
    border-radius: 999px;
    border: 2px solid color-mix(in srgb, var(--portal-surface) 80%, white);
    background: rgb(245 158 11);
    box-shadow: 0 0 0 1px rgb(245 158 11 / 0.35);
}

.notification-auto-close__range::-moz-range-thumb {
    width: 1rem;
    height: 1rem;
    border-radius: 999px;
    border: 2px solid color-mix(in srgb, var(--portal-surface) 80%, white);
    background: rgb(245 158 11);
    box-shadow: 0 0 0 1px rgb(245 158 11 / 0.35);
    cursor: pointer;
}

.notification-auto-close__range::-moz-range-track {
    height: 0.35rem;
    border-radius: 999px;
    background: color-mix(in srgb, var(--portal-border) 55%, transparent);
}
</style>
