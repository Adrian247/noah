<script setup lang="ts">
import { nextTick, ref } from 'vue';
import { placeFloatingPanel } from '@/lib/floatingUi';

const props = withDefaults(
    defineProps<{
        label: string;
        enabled?: boolean;
        placement?: 'right' | 'above';
    }>(),
    { enabled: true, placement: 'right' },
);

const anchorRef = ref<HTMLElement | null>(null);
const tipRef = ref<HTMLElement | null>(null);
const visible = ref(false);
const style = ref<{ top: string; left: string }>({ top: '0px', left: '0px' });

async function updatePosition() {
    const el = anchorRef.value;
    const tip = tipRef.value;
    if (!el || !tip) {
        return;
    }
    const rect = el.getBoundingClientRect();
    const prefer = props.placement === 'above' ? 'above' : 'right';
    const { top, left } = placeFloatingPanel(rect, tip.offsetWidth, tip.offsetHeight, { prefer });
    style.value = {
        top: `${top}px`,
        left: `${left}px`,
    };
}

async function show() {
    if (!props.enabled) {
        return;
    }
    visible.value = true;
    await nextTick();
    requestAnimationFrame(() => {
        void updatePosition();
    });
}

function hide() {
    visible.value = false;
}

function onFocusOut(event: FocusEvent) {
    const next = event.relatedTarget as Node | null;
    if (next && anchorRef.value?.contains(next)) {
        return;
    }
    hide();
}

defineExpose({ hide });
</script>

<template>
    <span
        ref="anchorRef"
        class="sidebar-nav-tooltip-anchor"
        @mouseenter="show"
        @mouseleave="hide"
        @focusin="show"
        @focusout="onFocusOut"
    >
        <slot />
    </span>

    <Teleport to="body">
        <div
            v-if="visible && enabled"
            ref="tipRef"
            role="tooltip"
            class="sidebar-floating-tooltip"
            :style="style"
        >
            {{ label }}
        </div>
    </Teleport>
</template>
