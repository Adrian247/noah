<script setup lang="ts">
import { computed } from 'vue';
import { storeToRefs } from 'pinia';
import PhoenixBrand from '@/components/ui/PhoenixBrand.vue';
import { notificationEntryAxis } from '@/lib/notificationPreferences';
import { useNotificationPreferencesStore } from '@/stores/notificationPreferences';
import { useToastStore, type ToastVariant } from '@/stores/toast';

const toast = useToastStore();
const prefs = useNotificationPreferencesStore();
const { position } = storeToRefs(prefs);

const hostClass = computed(() => [
    `app-toast-host--${position.value}`,
    `app-toast-host--${notificationEntryAxis(position.value)}`,
]);

const variantClass: Record<ToastVariant, string> = {
    success: 'app-toast--success',
    danger: 'app-toast--danger',
    warning: 'app-toast--warning',
    info: 'app-toast--info',
};

/** Logo (480) → shell (520@450) → texto (400@1000) → glow (560@1400) */
const TOAST_ENTER_MS = 2000;
const TOAST_LEAVE_MS = 320;

function onToastEnter(el: Element, done: () => void) {
    el.classList.add('app-toast--playing');
    window.setTimeout(() => {
        el.classList.remove('app-toast--playing');
        done();
    }, TOAST_ENTER_MS);
}

function onToastLeave(_el: Element, done: () => void) {
    window.setTimeout(done, TOAST_LEAVE_MS);
}
</script>

<template>
    <Teleport to="body">
        <div
            class="app-toast-host pointer-events-none fixed z-[250] flex flex-col gap-3"
            :class="hostClass"
            aria-live="polite"
            aria-relevant="additions"
        >
            <TransitionGroup
                name="app-toast"
                tag="div"
                class="app-toast-stack"
                @enter="onToastEnter"
                @leave="onToastLeave"
            >
                <article
                    v-for="item in toast.items"
                    :key="item.id"
                    class="app-toast pointer-events-auto"
                    :class="variantClass[item.variant]"
                    role="alert"
                >
                    <div class="app-toast__logo-flight" aria-hidden="true">
                        <div class="app-toast__logo-trail" />
                        <div class="app-toast__logo-trail app-toast__logo-trail--soft" />
                        <div class="app-toast__logo-wing app-toast__logo-wing--lead">
                            <PhoenixBrand size="sm" animated />
                        </div>
                    </div>

                    <div class="app-toast__shell login-glass-premium">
                        <div
                            class="app-toast__menu-fx sidebar-collapse-fx sidebar-collapse-fx--collapse"
                            aria-hidden="true"
                        >
                            <div class="sidebar-collapse-fx__beam" />
                            <div class="sidebar-collapse-fx__glow app-toast__menu-fx-glow" />
                            <div class="sidebar-collapse-fx__scan" />
                        </div>

                        <div class="app-toast__noise login-grain" aria-hidden="true" />
                        <div class="app-toast__glow" aria-hidden="true" />

                        <div class="app-toast__body">
                            <div class="app-toast__logo-spacer" aria-hidden="true" />
                            <div class="app-toast__content">
                                <p class="app-toast__message min-w-0 flex-1 text-sm font-medium leading-snug">
                                    {{ item.message }}
                                </p>
                                <button
                                    type="button"
                                    class="app-toast__dismiss shrink-0 rounded-lg p-1"
                                    aria-label="Cerrar"
                                    @click="toast.dismiss(item.id)"
                                >
                                    <span aria-hidden="true" class="block text-base leading-none">×</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </article>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
