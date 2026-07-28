<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import type { TourStep } from '@/lib/onboarding/tourSteps';
import { useProductTour } from '@/composables/useProductTour';
import AppButton from '@/components/ui/AppButton.vue';

type Rect = { top: number; left: number; width: number; height: number };

const {
    active,
    stepIndex,
    muted,
    currentStep,
    isFirst,
    isLast,
    progressLabel,
    next,
    prev,
    skip,
    toggleMute,
} = useProductTour();

const router = useRouter();

const targetRect = ref<Rect | null>(null);
const cardStyle = ref<Record<string, string>>({});
const audioEl = ref<HTMLAudioElement | null>(null);
const syncing = ref(false);
let syncGeneration = 0;
let pinnedNavEl: HTMLElement | null = null;
let litSidebarEl: HTMLElement | null = null;

function clearTourDomEffects() {
    pinnedNavEl?.classList.remove('product-tour-nav-pin');
    pinnedNavEl = null;
    litSidebarEl?.classList.remove('product-tour-sidebar-lit');
    litSidebarEl = null;
}

function applyTourDomEffects(step: TourStep | null, el: Element | null) {
    clearTourDomEffects();
    if (!step || !el || !(el instanceof HTMLElement)) {
        return;
    }
    if (step.spotlight === 'nav') {
        pinnedNavEl = el;
        el.classList.add('product-tour-nav-pin');
        el.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
    }
    if (step.spotlight === 'sidebar') {
        const sidebar = el.closest('aside') ?? el;
        if (sidebar instanceof HTMLElement) {
            litSidebarEl = sidebar;
            sidebar.classList.add('product-tour-sidebar-lit');
        }
    }
}

function queryTarget(selector?: string): Element | null {
    if (!selector) {
        return null;
    }
    return document.querySelector(selector);
}

async function waitForTarget(selector?: string, maxMs = 3000): Promise<void> {
    if (!selector) {
        return;
    }
    const deadline = Date.now() + maxMs;
    while (Date.now() < deadline) {
        if (queryTarget(selector)) {
            return;
        }
        await new Promise((r) => window.setTimeout(r, 60));
    }
}

function setCardBottomCenter() {
    cardStyle.value = {
        bottom: '1.25rem',
        left: '50%',
        transform: 'translateX(-50%)',
        maxWidth: '24rem',
    };
}

function setCardCentered() {
    targetRect.value = null;
    cardStyle.value = {
        top: '50%',
        left: '50%',
        transform: 'translate(-50%, -50%)',
        maxWidth: '26rem',
    };
}

function measureTarget(step: TourStep | null) {
    if (!step?.target) {
        clearTourDomEffects();
        setCardCentered();
        return;
    }

    const el = queryTarget(step.target);
    if (!el) {
        clearTourDomEffects();
        setCardCentered();
        return;
    }

    const pad =
        step.spotlight === 'nav' ? (step.padding ?? 10) : step.spotlight === 'sidebar' ? (step.padding ?? 6) : (step.padding ?? 8);
    const r = el.getBoundingClientRect();
    const viewportH = window.innerHeight;
    const viewportW = window.innerWidth;
    const cardEstimate = 260;

    targetRect.value = {
        top: Math.max(8, r.top - pad),
        left: Math.max(8, r.left - pad),
        width: r.width + pad * 2,
        height: r.height + pad * 2,
    };

    applyTourDomEffects(step, el);

    // Menú lateral u otros targets muy altos: spotlight visible, tarjeta fija abajo.
    if (targetRect.value.height > viewportH * 0.42) {
        setCardBottomCenter();
        return;
    }

    const cardTop = targetRect.value.top + targetRect.value.height + 16;
    if (cardTop + cardEstimate < viewportH) {
        cardStyle.value = {
            top: `${cardTop}px`,
            left: `${Math.min(Math.max(16, targetRect.value.left), viewportW - 320)}px`,
            maxWidth: '24rem',
            transform: 'none',
            bottom: 'auto',
        };
        return;
    }

    const cardAboveTop = targetRect.value.top - cardEstimate - 16;
    if (cardAboveTop >= 16) {
        cardStyle.value = {
            top: `${cardAboveTop}px`,
            left: `${Math.min(Math.max(16, targetRect.value.left), viewportW - 320)}px`,
            maxWidth: '24rem',
            transform: 'none',
            bottom: 'auto',
        };
        return;
    }

    setCardBottomCenter();
}

async function applyStepRoute(step: TourStep | null): Promise<boolean> {
    if (!step?.route) {
        return true;
    }
    if (router.currentRoute.value.path === step.route) {
        await new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));
        return true;
    }
    try {
        await router.push(step.route);
        await router.isReady();
        await new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));
        return router.currentRoute.value.path === step.route
            || router.currentRoute.value.path.startsWith(`${step.route}/`);
    } catch {
        return false;
    }
}

function playStepAudio(step: TourStep | null) {
    const audio = audioEl.value;
    if (!audio || !step || muted.value) {
        return;
    }
    audio.pause();
    audio.src = step.audioUrl;
    void audio.play().catch(() => {
        /* autoplay blocked or missing file */
    });
}

async function syncStep() {
    const generation = ++syncGeneration;
    syncing.value = true;
    const step = currentStep.value;
    try {
        if (!step) {
            return;
        }
        await applyStepRoute(step);
        if (generation !== syncGeneration) {
            return;
        }
        await waitForTarget(step.target);
        if (generation !== syncGeneration) {
            return;
        }
        measureTarget(step);
        playStepAudio(step);
    } finally {
        if (generation === syncGeneration) {
            syncing.value = false;
        }
    }
}

watch(
    () => [active.value, stepIndex.value] as const,
    ([isActive]) => {
        if (!isActive) {
            syncGeneration += 1;
            audioEl.value?.pause();
            syncing.value = false;
            clearTourDomEffects();
            return;
        }
        void syncStep();
    },
);

watch(muted, (isMuted) => {
    if (isMuted) {
        audioEl.value?.pause();
    } else if (active.value) {
        playStepAudio(currentStep.value);
    }
});

function onResize() {
    if (active.value) {
        measureTarget(currentStep.value);
    }
}

onMounted(() => {
    window.addEventListener('resize', onResize);
    window.addEventListener('scroll', onResize, true);
});

onUnmounted(() => {
    window.removeEventListener('resize', onResize);
    window.removeEventListener('scroll', onResize, true);
    clearTourDomEffects();
});

const spotlightClass = computed(() => {
    const mode = currentStep.value?.spotlight ?? 'panel';
    return `product-tour-spotlight product-tour-spotlight--${mode}`;
});

const spotlightStyle = computed(() => {
    const r = targetRect.value;
    if (!r) {
        return null;
    }
    return {
        top: `${r.top}px`,
        left: `${r.left}px`,
        width: `${r.width}px`,
        height: `${r.height}px`,
    };
});
</script>

<template>
    <Teleport to="body">
        <audio ref="audioEl" preload="auto" />
        <div
            v-if="active && currentStep"
            class="product-tour-root"
            role="dialog"
            aria-modal="true"
            :aria-label="currentStep.title"
        >
            <div class="product-tour-backdrop" aria-hidden="true" />
            <div
                v-if="spotlightStyle"
                :class="spotlightClass"
                :style="spotlightStyle"
                aria-hidden="true"
            />
            <div class="product-tour-card" :style="cardStyle">
                <p class="product-tour-card__progress">{{ progressLabel }}</p>
                <h2 class="product-tour-card__title">{{ currentStep.title }}</h2>
                <p class="product-tour-card__body">{{ currentStep.body }}</p>
                <div class="product-tour-card__actions">
                    <div class="product-tour-card__links">
                        <button type="button" class="product-tour-link" @click="skip()">Omitir tour</button>
                        <button type="button" class="product-tour-link" @click="toggleMute()">
                            {{ muted ? 'Activar voz' : 'Silenciar' }}
                        </button>
                    </div>
                    <div class="product-tour-card__nav">
                        <AppButton
                            v-if="!isFirst"
                            type="button"
                            variant="secondary"
                            :disabled="syncing"
                            @click="prev()"
                        >
                            Anterior
                        </AppButton>
                        <AppButton type="button" :disabled="syncing" @click="next()">
                            {{ syncing ? 'Cargando…' : isLast ? 'Finalizar' : 'Siguiente' }}
                        </AppButton>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.product-tour-root {
    position: fixed;
    inset: 0;
    z-index: 10050;
    pointer-events: none;
}

.product-tour-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(2, 6, 23, 0.78);
    pointer-events: auto;
}

.product-tour-spotlight {
    position: fixed;
    border-radius: 12px;
    box-shadow: 0 0 0 9999px rgba(2, 6, 23, 0.78);
    pointer-events: none;
    z-index: 2;
    outline: 2px solid rgba(251, 191, 36, 0.9);
    outline-offset: 2px;
}

.product-tour-spotlight--nav {
    border-radius: 14px;
    outline-width: 3px;
    outline-offset: 3px;
    box-shadow:
        0 0 0 9999px rgba(2, 6, 23, 0.82),
        0 0 28px 6px rgba(251, 191, 36, 0.55);
    animation: product-tour-spot-pulse 1.5s ease-in-out infinite;
}

.product-tour-spotlight--sidebar {
    border-radius: 16px;
    outline-width: 3px;
    outline-color: rgba(251, 191, 36, 0.95);
    box-shadow:
        0 0 0 9999px rgba(2, 6, 23, 0.8),
        inset 0 0 0 1px rgba(255, 255, 255, 0.08);
}

.product-tour-spotlight--panel {
    box-shadow:
        0 0 0 9999px rgba(2, 6, 23, 0.78),
        0 8px 32px rgba(0, 0, 0, 0.35);
}

@keyframes product-tour-spot-pulse {
    0%,
    100% {
        outline-color: rgba(251, 191, 36, 0.85);
    }
    50% {
        outline-color: rgba(253, 224, 71, 1);
    }
}

.product-tour-card {
    position: fixed;
    z-index: 3;
    pointer-events: auto;
    padding: 1.25rem 1.35rem;
    border-radius: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(15, 23, 42, 0.96);
    color: #f1f5f9;
    box-shadow: 0 24px 48px rgba(0, 0, 0, 0.45);
    width: min(24rem, calc(100vw - 2rem));
}

.product-tour-card__progress {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #94a3b8;
    margin-bottom: 0.35rem;
}

.product-tour-card__title {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.product-tour-card__body {
    font-size: 0.9rem;
    line-height: 1.5;
    color: #cbd5e1;
    margin-bottom: 1rem;
}

.product-tour-card__actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.product-tour-card__links {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem 0.75rem;
}

.product-tour-card__nav {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.product-tour-link {
    font-size: 0.8rem;
    color: #94a3b8;
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.25rem 0;
}

.product-tour-link:hover {
    color: #e2e8f0;
}

[data-theme='light'] .product-tour-card {
    background: #fff;
    color: #0f172a;
    border-color: rgba(15, 23, 42, 0.1);
}

[data-theme='light'] .product-tour-card__body {
    color: #475569;
}
</style>
