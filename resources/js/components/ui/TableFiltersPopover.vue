<script setup lang="ts">
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import NavIcon from '@/components/ui/NavIcon.vue';

const props = defineProps<{
    active?: boolean;
    title?: string;
}>();

const open = ref(false);
const triggerRef = ref<HTMLButtonElement | null>(null);
const panelRef = ref<HTMLElement | null>(null);
const panelStyle = ref<{ top: string; left: string; maxHeight: string }>({
    top: '0px',
    left: '0px',
    maxHeight: '24rem',
});

let ignoreDocumentCloseUntil = 0;

async function updatePanelPosition() {
    await nextTick();
    const trigger = triggerRef.value;
    const panel = panelRef.value;
    if (!trigger || !panel) {
        return;
    }
    const rect = trigger.getBoundingClientRect();
    const panelWidth = panel.offsetWidth || 300;
    const margin = 8;
    let left = rect.right - panelWidth;
    left = Math.max(margin, Math.min(left, window.innerWidth - panelWidth - margin));
    const top = rect.bottom + margin;
    const maxHeight = Math.max(160, window.innerHeight - top - margin);
    panelStyle.value = {
        top: `${top}px`,
        left: `${left}px`,
        maxHeight: `${maxHeight}px`,
    };
}

function onDocumentPointerDown(event: PointerEvent) {
    if (!open.value || Date.now() < ignoreDocumentCloseUntil) {
        return;
    }
    const target = event.target as Node;
    if (triggerRef.value?.contains(target) || panelRef.value?.contains(target)) {
        return;
    }
    open.value = false;
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        open.value = false;
    }
}

function onTriggerClick(event: MouseEvent) {
    event.preventDefault();
    event.stopPropagation();
    open.value = !open.value;
    if (open.value) {
        ignoreDocumentCloseUntil = Date.now() + 200;
    }
}

watch(open, (isOpen) => {
    if (isOpen) {
        void updatePanelPosition();
    }
});

onMounted(() => {
    document.addEventListener('pointerdown', onDocumentPointerDown, true);
    document.addEventListener('keydown', onKeydown);
    window.addEventListener('resize', updatePanelPosition);
    window.addEventListener('scroll', updatePanelPosition, true);
});

onUnmounted(() => {
    document.removeEventListener('pointerdown', onDocumentPointerDown, true);
    document.removeEventListener('keydown', onKeydown);
    window.removeEventListener('resize', updatePanelPosition);
    window.removeEventListener('scroll', updatePanelPosition, true);
});
</script>

<template>
    <button
        ref="triggerRef"
        type="button"
        class="table-filters-popover__trigger icon-action-btn icon-action-btn--default"
        :class="{
            'table-filters-popover__trigger--open': open,
            'table-filters-popover__trigger--active': active,
        }"
        aria-haspopup="dialog"
        :aria-expanded="open"
        :aria-label="title ?? 'Filtros'"
        @click="onTriggerClick"
    >
        <NavIcon name="filter" size="sm" />
    </button>

    <Teleport to="body">
        <div
            v-if="open"
            ref="panelRef"
            class="table-filters-popover__panel"
            role="dialog"
            :aria-label="title ?? 'Filtros'"
            :style="panelStyle"
            @click.stop
        >
            <p class="table-filters-popover__title">{{ title ?? 'Filtros' }}</p>
            <div class="table-filters-popover__body">
                <slot />
            </div>
            <div class="table-filters-popover__footer">
                <button type="button" class="table-filters-popover__close" @click="open = false">
                    Cerrar
                </button>
            </div>
        </div>
    </Teleport>
</template>
