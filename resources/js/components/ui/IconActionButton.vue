<script setup lang="ts">
import { onBeforeUnmount, ref, useId } from 'vue';
import { RouterLink, type RouteLocationRaw } from 'vue-router';
import NavIcon, { type NavIconName } from '@/components/ui/NavIcon.vue';

const props = withDefaults(
    defineProps<{
        icon: NavIconName;
        label: string;
        variant?: 'default' | 'danger';
        tooltipPlacement?: 'top' | 'bottom';
        disabled?: boolean;
        to?: RouteLocationRaw;
    }>(),
    { variant: 'default', tooltipPlacement: 'top', disabled: false },
);

defineEmits<{
    click: [];
}>();

const wrapRef = ref<HTMLElement | null>(null);
const tipId = useId();
const showTip = ref(false);
const tipPlacement = ref<'top' | 'bottom'>('top');
const tipStyle = ref<{ top: string; left: string }>({ top: '0px', left: '0px' });

let scrollListener: (() => void) | null = null;

function clearScrollListener() {
    if (scrollListener) {
        window.removeEventListener('scroll', scrollListener, true);
        scrollListener = null;
    }
}

function updateTipPosition() {
    const el = wrapRef.value;
    if (!el) {
        return;
    }

    const rect = el.getBoundingClientRect();
    const gap = 8;
    const margin = 10;
    const estimatedTipHeight = 32;

    let placement = props.tooltipPlacement;
    if (placement === 'top' && rect.top < estimatedTipHeight + gap + margin) {
        placement = 'bottom';
    } else if (placement === 'bottom' && rect.bottom + estimatedTipHeight + gap > window.innerHeight - margin) {
        placement = 'top';
    }

    tipPlacement.value = placement;

    const centerX = rect.left + rect.width / 2;
    const clampedX = Math.min(window.innerWidth - margin, Math.max(margin, centerX));

    const top =
        placement === 'top'
            ? Math.max(margin, rect.top - gap)
            : Math.min(window.innerHeight - margin, rect.bottom + gap);

    tipStyle.value = {
        top: `${top}px`,
        left: `${clampedX}px`,
    };
}

function showTooltip() {
    showTip.value = true;
    requestAnimationFrame(() => updateTipPosition());

    clearScrollListener();
    scrollListener = () => {
        showTip.value = false;
        clearScrollListener();
    };
    window.addEventListener('scroll', scrollListener, true);
}

function hideTooltip() {
    showTip.value = false;
    clearScrollListener();
}

function onFocusOut(event: FocusEvent) {
    const next = event.relatedTarget as Node | null;
    if (next && wrapRef.value?.contains(next)) {
        return;
    }
    hideTooltip();
}

onBeforeUnmount(() => {
    clearScrollListener();
});
</script>

<template>
    <span
        ref="wrapRef"
        class="icon-action-btn-wrap"
        @mouseenter="showTooltip"
        @mouseleave="hideTooltip"
        @focusin="showTooltip"
        @focusout="onFocusOut"
    >
        <RouterLink
            v-if="to"
            :to="to"
            class="icon-action-btn"
            :class="variant === 'danger' ? 'icon-action-btn--danger' : 'icon-action-btn--default'"
            :aria-label="label"
            :aria-describedby="showTip ? tipId : undefined"
        >
            <NavIcon :name="icon" size="sm" />
        </RouterLink>
        <button
            v-else
            type="button"
            class="icon-action-btn"
            :class="variant === 'danger' ? 'icon-action-btn--danger' : 'icon-action-btn--default'"
            :aria-label="label"
            :aria-describedby="showTip ? tipId : undefined"
            :disabled="disabled"
            @click="$emit('click')"
        >
            <NavIcon :name="icon" size="sm" />
        </button>
    </span>

    <Teleport to="body">
        <div
            v-if="showTip"
            :id="tipId"
            role="tooltip"
            class="icon-action-floating-tooltip"
            :class="tipPlacement === 'top' ? 'icon-action-floating-tooltip--top' : 'icon-action-floating-tooltip--bottom'"
            :style="tipStyle"
        >
            {{ label }}
        </div>
    </Teleport>
</template>
