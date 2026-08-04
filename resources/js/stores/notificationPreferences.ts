import { defineStore } from 'pinia';
import { computed, ref, watch } from 'vue';
import {
    clampAutoCloseSeconds,
    loadNotificationPreferences,
    NOTIFICATION_SOUND_URLS,
    saveNotificationPreferences,
    type NotificationPreferences,
    type ToastPosition,
} from '@/lib/notificationPreferences';
import type { ToastVariant } from '@/stores/toast';

let audioEl: HTMLAudioElement | null = null;

export const useNotificationPreferencesStore = defineStore('notificationPreferences', () => {
    const initial = loadNotificationPreferences();
    const position = ref<ToastPosition>(initial.position);
    const soundEnabled = ref(initial.soundEnabled);
    const autoCloseEnabled = ref(initial.autoCloseEnabled);
    const autoCloseSeconds = ref(initial.autoCloseSeconds);

    const autoCloseDurationMs = computed(() =>
        autoCloseEnabled.value ? autoCloseSeconds.value * 1000 : 0,
    );

    function persist() {
        saveNotificationPreferences({
            position: position.value,
            soundEnabled: soundEnabled.value,
            autoCloseEnabled: autoCloseEnabled.value,
            autoCloseSeconds: autoCloseSeconds.value,
        });
    }

    watch([position, soundEnabled, autoCloseEnabled, autoCloseSeconds], persist);

    function setPosition(next: ToastPosition) {
        position.value = next;
    }

    function setSoundEnabled(next: boolean) {
        soundEnabled.value = next;
    }

    function setAutoCloseEnabled(next: boolean) {
        autoCloseEnabled.value = next;
    }

    function setAutoCloseSeconds(next: number) {
        autoCloseSeconds.value = clampAutoCloseSeconds(next);
    }

    function playSound(variant: ToastVariant) {
        if (!soundEnabled.value || typeof window === 'undefined') {
            return;
        }
        if (!audioEl) {
            audioEl = new Audio();
            audioEl.preload = 'auto';
            // Clip regenerados ya son suaves; volumen bajo de reproducción como margen.
            audioEl.volume = 0.35;
        }
        const url = NOTIFICATION_SOUND_URLS[variant];
        audioEl.pause();
        audioEl.src = url;
        audioEl.volume = 0.35;
        audioEl.currentTime = 0;
        void audioEl.play().catch(() => {
            /* autoplay policy or missing file */
        });
    }

    function applySnapshot(snapshot: NotificationPreferences) {
        position.value = snapshot.position;
        soundEnabled.value = snapshot.soundEnabled;
        autoCloseEnabled.value = snapshot.autoCloseEnabled;
        autoCloseSeconds.value = clampAutoCloseSeconds(snapshot.autoCloseSeconds);
    }

    return {
        position,
        soundEnabled,
        autoCloseEnabled,
        autoCloseSeconds,
        autoCloseDurationMs,
        setPosition,
        setSoundEnabled,
        setAutoCloseEnabled,
        setAutoCloseSeconds,
        playSound,
        applySnapshot,
    };
});
