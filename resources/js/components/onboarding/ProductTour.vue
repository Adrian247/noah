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
let litTargetEl: HTMLElement | null = null;

const SCRIM = 'rgba(15, 23, 42, 0.42)';
const SCRIM_FULL = 'rgba(15, 23, 42, 0.48)';

function clearTourDomEffects() {
    pinnedNavEl?.classList.remove('product-tour-nav-pin');
    pinnedNavEl = null;
    litSidebarEl?.classList.remove('product-tour-sidebar-lit');
    litSidebarEl = null;
    litTargetEl?.classList.remove('product-tour-target-lit');
    litTargetEl = null;
}

function applyTourDomEffects(step: TourStep | null, el: Element | null) {
    clearTourDomEffects();
    if (!step || !el || !(el instanceof HTMLElement)) {
        return;
    }

    const bounds = el.getBoundingClientRect();
    const isCompactTarget =
        step.spotlight === 'nav' ||
        step.spotlight === 'sidebar' ||
        bounds.height < window.innerHeight * 0.45;

    // Solo elevar targets compactos. Páginas enteras se ven por el recorte del scrim.
    if (isCompactTarget) {
        litTargetEl = el;
        el.classList.add('product-tour-target-lit');
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
    if (step.spotlight === 'panel') {
        el.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
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
        step.spotlight === 'nav' ? (step.padding ?? 10) : step.spotlight === 'sidebar' ? (step.padding ?? 6) : (step.padding ?? 10);
    const r = el.getBoundingClientRect();
    const viewportH = window.innerHeight;
    const viewportW = window.innerWidth;
    const cardEstimate = 260;

    targetRect.value = {
        top: Math.max(8, r.top - pad),
        left: Math.max(8, r.left - pad),
        width: Math.min(viewportW - 16, r.width + pad * 2),
        height: Math.min(viewportH - 16, r.height + pad * 2),
    };

    applyTourDomEffects(step, el);

    // Menú lateral u otros targets muy altos: spotlight visible, tarjeta fija abajo.
    if (step.spotlight === 'sidebar' || targetRect.value.height > viewportH * 0.42) {
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

let speechUtterance: SpeechSynthesisUtterance | null = null;

function stopStepSpeech() {
    if (typeof window !== 'undefined' && window.speechSynthesis) {
        window.speechSynthesis.cancel();
    }
    speechUtterance = null;
}

function speakStepNarration(step: TourStep) {
    if (muted.value || typeof window === 'undefined' || !window.speechSynthesis) {
        return;
    }
    stopStepSpeech();
    const utterance = new SpeechSynthesisUtterance(step.body);
    utterance.lang = 'es-MX';
    utterance.rate = 0.95;
    speechUtterance = utterance;
    window.speechSynthesis.speak(utterance);
}

function playStepAudio(step: TourStep | null) {
    const audio = audioEl.value;
    if (!audio || !step || muted.value) {
        return;
    }
    stopStepSpeech();
    audio.pause();
    const sources = [step.audioUrl, step.audioUrlAlt].filter(
        (url): url is string => typeof url === 'string' && url.length > 0,
    );
    let sourceIndex = 0;

    const tryNextSource = () => {
        if (sourceIndex >= sources.length) {
            speakStepNarration(step);
            return;
        }
        audio.src = sources[sourceIndex]!;
        sourceIndex += 1;
        const onMissing = () => {
            audio.removeEventListener('error', onMissing);
            tryNextSource();
        };
        audio.addEventListener('error', onMissing, { once: true });
        void audio.play().catch(() => {
            audio.removeEventListener('error', onMissing);
            tryNextSource();
        });
    };

    tryNextSource();
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
        // Segunda medición tras layout/scroll de la página destino.
        await new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));
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
            stopStepSpeech();
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
        stopStepSpeech();
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

/** Scrim con recorte: la sección resaltada se ve nítida (sin capa oscura encima). */
const scrimStyle = computed(() => {
    const r = targetRect.value;
    if (!r) {
        return {
            background: SCRIM_FULL,
            clipPath: 'none',
        };
    }
    const { top, left, width, height } = r;
    const right = left + width;
    const bottom = top + height;
    return {
        background: SCRIM,
        clipPath: `polygon(evenodd, 0% 0%, 100% 0%, 100% 100%, 0% 100%, ${left}px ${top}px, ${right}px ${top}px, ${right}px ${bottom}px, ${left}px ${bottom}px)`,
    };
});
</script>

<template>
    <Teleport to="body">
        <audio ref="audioEl" preload="auto" />
        <template v-if="active && currentStep">
            <div
                class="product-tour-root"
                role="presentation"
                aria-hidden="true"
            >
                <div
                    class="product-tour-scrim"
                    :style="scrimStyle"
                    aria-hidden="true"
                />
                <div
                    v-if="spotlightStyle"
                    :class="spotlightClass"
                    :style="spotlightStyle"
                    aria-hidden="true"
                />
            </div>
            <div
                class="product-tour-card"
                role="dialog"
                aria-modal="true"
                :aria-label="currentStep.title"
                :style="cardStyle"
            >
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
        </template>
    </Teleport>
</template>

<style scoped>
.product-tour-root {
    position: fixed;
    inset: 0;
    z-index: 10050;
    pointer-events: none;
}

.product-tour-scrim {
    position: absolute;
    inset: 0;
    pointer-events: auto;
    /* Oscuridad moderada: no tapa el contenido del recorte. */
    background: rgba(15, 23, 42, 0.42);
}

.product-tour-spotlight {
    position: fixed;
    border-radius: 12px;
    /* Solo aro + glow; el oscurecido lo hace el scrim con clip-path. */
    box-shadow:
        0 0 0 2px rgba(251, 191, 36, 0.95),
        0 0 0 6px rgba(251, 191, 36, 0.22),
        0 12px 36px rgba(15, 23, 42, 0.18);
    pointer-events: none;
    z-index: 2;
    background: transparent;
}

.product-tour-spotlight--nav {
    border-radius: 14px;
    box-shadow:
        0 0 0 3px rgba(251, 191, 36, 0.98),
        0 0 0 8px rgba(251, 191, 36, 0.28),
        0 0 28px 4px rgba(251, 191, 36, 0.45);
    animation: product-tour-spot-pulse 1.5s ease-in-out infinite;
}

.product-tour-spotlight--sidebar {
    border-radius: 16px;
    box-shadow:
        0 0 0 3px rgba(251, 191, 36, 0.95),
        0 0 0 8px rgba(251, 191, 36, 0.2),
        inset 0 0 0 1px rgba(255, 255, 255, 0.12);
}

.product-tour-spotlight--panel {
    border-radius: 14px;
    box-shadow:
        0 0 0 2px rgba(251, 191, 36, 0.92),
        0 0 0 7px rgba(251, 191, 36, 0.18),
        0 10px 28px rgba(15, 23, 42, 0.16);
}

@keyframes product-tour-spot-pulse {
    0%,
    100% {
        box-shadow:
            0 0 0 3px rgba(251, 191, 36, 0.9),
            0 0 0 8px rgba(251, 191, 36, 0.22),
            0 0 24px 4px rgba(251, 191, 36, 0.4);
    }
    50% {
        box-shadow:
            0 0 0 3px rgba(253, 224, 71, 1),
            0 0 0 10px rgba(253, 224, 71, 0.32),
            0 0 32px 6px rgba(253, 224, 71, 0.5);
    }
}

.product-tour-card {
    position: fixed;
    z-index: 10080;
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

:global([data-theme='light']) .product-tour-card {
    background: #fff;
    color: #0f172a;
    border-color: rgba(15, 23, 42, 0.1);
}

:global([data-theme='light']) .product-tour-card__body {
    color: #475569;
}

:global([data-theme='light']) .product-tour-card__progress,
:global([data-theme='light']) .product-tour-link {
    color: #64748b;
}

:global([data-theme='light']) .product-tour-link:hover {
    color: #0f172a;
}

:global([data-theme='light']) .product-tour-scrim {
    background: rgba(15, 23, 42, 0.32) !important;
}
</style>
