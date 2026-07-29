<script setup lang="ts">
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import NavIcon from '@/components/ui/NavIcon.vue';

const props = withDefaults(
    defineProps<{
        placeholder?: string;
    }>(),
    { placeholder: 'Buscar…' },
);

const query = defineModel<string>({ default: '' });

const open = ref(false);
const triggerRef = ref<HTMLButtonElement | null>(null);
const panelRef = ref<HTMLElement | null>(null);
const inputRef = ref<HTMLInputElement | null>(null);
const panelStyle = ref<{ top: string; left: string; maxHeight: string }>({
    top: '0px',
    left: '0px',
    maxHeight: '12rem',
});

let ignoreDocumentCloseUntil = 0;

const hasQuery = () => query.value.trim().length > 0;

async function updatePanelPosition() {
    await nextTick();
    const trigger = triggerRef.value;
    const panel = panelRef.value;
    if (!trigger || !panel) {
        return;
    }
    const rect = trigger.getBoundingClientRect();
    const panelWidth = panel.offsetWidth || 280;
    const margin = 8;
    let left = rect.right - panelWidth;
    left = Math.max(margin, Math.min(left, window.innerWidth - panelWidth - margin));
    const top = rect.bottom + margin;
    const maxHeight = Math.max(80, window.innerHeight - top - margin);
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
        void nextTick(() => inputRef.value?.focus());
    }
}

function clearQuery() {
    query.value = '';
    void nextTick(() => inputRef.value?.focus());
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
        class="table-search-popover__trigger icon-action-btn icon-action-btn--default"
        :class="{
            'table-search-popover__trigger--open': open,
            'table-search-popover__trigger--active': hasQuery(),
        }"
        aria-haspopup="dialog"
        :aria-expanded="open"
        aria-label="Buscar en la tabla"
        @click="onTriggerClick"
    >
        <NavIcon name="search" size="sm" />
    </button>

    <Teleport to="body">
        <div
            v-if="open"
            ref="panelRef"
            class="table-search-popover__panel"
            role="dialog"
            aria-label="Buscar"
            :style="panelStyle"
            @click.stop
        >
            <label class="table-search-popover__field">
                <span class="sr-only">Buscar</span>
                <input
                    ref="inputRef"
                    v-model="query"
                    type="search"
                    class="table-search-popover__input"
                    :placeholder="placeholder"
                    autocomplete="off"
                />
            </label>
            <div class="table-search-popover__footer">
                <button
                    v-if="hasQuery()"
                    type="button"
                    class="table-search-popover__clear"
                    @click="clearQuery"
                >
                    Limpiar
                </button>
                <button type="button" class="table-search-popover__close" @click="open = false">
                    Cerrar
                </button>
            </div>
        </div>
    </Teleport>
</template>
