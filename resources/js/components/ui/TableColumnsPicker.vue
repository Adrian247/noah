<script setup lang="ts">
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import type { TableColumnChoice } from '@/lib/tableColumns';
import NavIcon from '@/components/ui/NavIcon.vue';

defineProps<{
    choices: TableColumnChoice[];
}>();

const emit = defineEmits<{
    toggle: [id: string, visible: boolean];
    reset: [];
}>();

const open = ref(false);
const triggerRef = ref<HTMLButtonElement | null>(null);
const panelRef = ref<HTMLElement | null>(null);
const panelStyle = ref<{ top: string; left: string; maxHeight: string }>({
    top: '0px',
    left: '0px',
    maxHeight: '20rem',
});

/** Evita que el mismo clic que abre el panel lo cierre al propagarse al documento. */
let ignoreDocumentCloseUntil = 0;

async function updatePanelPosition() {
    await nextTick();
    const trigger = triggerRef.value;
    const panel = panelRef.value;
    if (!trigger || !panel) {
        return;
    }
    const rect = trigger.getBoundingClientRect();
    const panelWidth = panel.offsetWidth || 256;
    const margin = 8;
    let left = rect.right - panelWidth;
    left = Math.max(margin, Math.min(left, window.innerWidth - panelWidth - margin));
    const top = rect.bottom + margin;
    const maxHeight = Math.max(120, window.innerHeight - top - margin);
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
        class="table-columns-picker__trigger icon-action-btn icon-action-btn--default"
        :class="{ 'table-columns-picker__trigger--open': open }"
        aria-haspopup="dialog"
        :aria-expanded="open"
        aria-label="Elegir columnas visibles"
        @click="onTriggerClick"
    >
        <NavIcon name="view-columns" size="sm" />
    </button>

    <Teleport to="body">
        <div
            v-if="open"
            ref="panelRef"
            class="table-columns-picker__panel"
            role="dialog"
            aria-label="Columnas visibles"
            :style="panelStyle"
            @click.stop
        >
            <p class="table-columns-picker__title">Columnas visibles</p>
            <ul class="table-columns-picker__list">
                <li v-for="choice in choices" :key="choice.id">
                    <label class="table-columns-picker__row">
                        <input
                            type="checkbox"
                            :checked="choice.visible"
                            :disabled="choice.locked"
                            @change="emit('toggle', choice.id, ($event.target as HTMLInputElement).checked)"
                        />
                        <span>{{ choice.label || choice.id }}</span>
                    </label>
                </li>
            </ul>
            <button type="button" class="table-columns-picker__reset" @click="emit('reset')">
                Restaurar predeterminadas
            </button>
        </div>
    </Teleport>
</template>
