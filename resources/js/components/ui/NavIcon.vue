<script setup lang="ts">
import { computed } from 'vue';

export type NavIconName =
    | 'home'
    | 'clipboard-list'
    | 'cube'
    | 'package'
    | 'truck'
    | 'map-pin'
    | 'layers'
    | 'document'
    | 'chart-bar'
    | 'workflow'
    | 'receipt'
    | 'cog'
    | 'shield'
    | 'users'
    | 'chevron-left'
    | 'chevron-right'
    | 'tags'
    | 'building';

const props = withDefaults(
    defineProps<{
        name: NavIconName;
        size?: 'sm' | 'md';
    }>(),
    { size: 'md' },
);

const sizeClass = computed(() => (props.size === 'sm' ? 'h-4 w-4' : 'h-5 w-5'));
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.75"
        stroke-linecap="round"
        stroke-linejoin="round"
        :class="sizeClass"
        aria-hidden="true"
    >
        <template v-if="name === 'home'">
            <path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z" />
        </template>
        <template v-else-if="name === 'clipboard-list'">
            <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" />
            <path d="M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v0a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2z" />
            <path d="M9 12h6M9 16h6" />
        </template>
        <template v-else-if="name === 'cube'">
            <path
                d="M12 2 4 6.5v11L12 22l8-4.5v-11L12 2zM4 6.5 12 11m8-4.5L12 11M12 11v11"
            />
        </template>
        <template v-else-if="name === 'package'">
            <path d="M12 3 3 7.5v9L12 21l9-4.5v-9L12 3z" />
            <path d="M12 12 3 7.5M12 12l9-4.5M12 12v9" />
        </template>
        <template v-else-if="name === 'truck'">
            <path d="M3 7h11v8H3zM14 10h4l3 3v2h-7z" />
            <circle cx="7" cy="17" r="2" />
            <circle cx="17" cy="17" r="2" />
        </template>
        <template v-else-if="name === 'map-pin'">
            <path d="M12 21s6-5.2 6-10a6 6 0 1 0-12 0c0 4.8 6 10 6 10z" />
            <circle cx="12" cy="11" r="2.5" />
        </template>
        <template v-else-if="name === 'layers'">
            <path d="M12 2 3 7l9 5 9-5-9-5zM3 12l9 5 9-5M3 17l9 5 9-5" />
        </template>
        <template v-else-if="name === 'document'">
            <path d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" />
            <path d="M14 3v6h6" />
        </template>
        <template v-else-if="name === 'chart-bar'">
            <path d="M4 20V10M10 20V4M16 20v-8M22 20H2" />
        </template>
        <template v-else-if="name === 'workflow'">
            <circle cx="6" cy="6" r="2.5" />
            <circle cx="18" cy="6" r="2.5" />
            <circle cx="12" cy="18" r="2.5" />
            <path d="M8 6h8M7.5 8.2 10.5 15.8M16.5 8.2 13.5 15.8" />
        </template>
        <template v-else-if="name === 'receipt'">
            <path d="M6 3h12v18l-2-1.5L14 21l-2-1.5L10 21l-2-1.5L6 21z" />
            <path d="M9 8h6M9 12h6M9 16h4" />
        </template>
        <template v-else-if="name === 'cog'">
            <circle cx="12" cy="12" r="3" />
            <path
                d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"
            />
        </template>
        <template v-else-if="name === 'shield'">
            <path d="M12 3 4 6v6c0 5 3.5 8.5 8 9 4.5-.5 8-4 8-9V6l-8-3z" />
            <path d="M9 12l2 2 4-4" />
        </template>
        <template v-else-if="name === 'users'">
            <circle cx="9" cy="8" r="3" />
            <path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6" />
            <circle cx="17" cy="9" r="2.5" />
            <path d="M15 20c.3-2.2 1.8-4 4-4" />
        </template>
        <template v-else-if="name === 'chevron-left'">
            <path d="M15 6 9 12l6 6" />
        </template>
        <template v-else-if="name === 'chevron-right'">
            <path d="M9 6l6 6-6 6" />
        </template>
        <template v-else-if="name === 'tags'">
            <path d="M5 7h6l6 6v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2z" />
            <circle cx="9" cy="9" r="1.5" fill="currentColor" stroke="none" />
        </template>
        <template v-else-if="name === 'building'">
            <path d="M4 20V8l8-5 8 5v12H4z" />
            <path d="M9 20v-5h6v5M9 10h.01M15 10h.01" />
        </template>
    </svg>
</template>
