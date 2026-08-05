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
    | 'activity'
    | 'cpu'
    | 'workflow'
    | 'receipt'
    | 'cog'
    | 'shield'
    | 'users'
    | 'chevron-left'
    | 'chevron-right'
    | 'tags'
    | 'building'
    | 'buildings'
    | 'briefcase'
    | 'factory'
    | 'boxes'
    | 'library'
    | 'archive'
    | 'pencil'
    | 'trash'
    | 'arrows-exchange'
    | 'eye'
    | 'check'
    | 'x'
    | 'chevron-up'
    | 'chevron-down'
    | 'copy'
    | 'power'
    | 'send'
    | 'columns'
    | 'view-columns'
    | 'download'
    | 'upload'
    | 'search'
    | 'filter'
    | 'wrench'
    | 'assistant';

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
        <template v-else-if="name === 'activity'">
            <path d="M22 12h-4l-3 7L9 5l-3 7H2" />
        </template>
        <template v-else-if="name === 'cpu'">
            <rect x="5" y="5" width="14" height="14" rx="2" />
            <path d="M9 9h6v6H9z" />
            <path d="M9 2v3M15 2v3M9 19v3M15 19v3M2 9h3M2 15h3M19 9h3M19 15h3" />
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
        <template v-else-if="name === 'buildings'">
            <path d="M3 21V10l5-3.5v14.5H3z" />
            <path d="M16 21V6l5-3v18h-5z" />
            <path d="M6 14h.01M6 17h.01M19 11h.01M19 14h.01" />
        </template>
        <template v-else-if="name === 'briefcase'">
            <rect x="3" y="7" width="18" height="13" rx="2" />
            <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
            <path d="M3 12h18" />
        </template>
        <template v-else-if="name === 'factory'">
            <path d="M2 20V11l4-2v9" />
            <path d="M6 20V9l4-2v13" />
            <path d="M10 20V7l4-2v15" />
            <path d="M14 20V5l6-2.5V20" />
            <path d="M2 20h20" />
            <path d="M17 8v2M17 12v2" />
        </template>
        <template v-else-if="name === 'boxes'">
            <path d="M4 10.5 8 8l4 2.5v6.5L8 19.5 4 17z" />
            <path d="M8 8 12 5.5 16 8" />
            <path d="M12 10.5 16 8l4 2.5v6.5L16 19.5 12 17z" />
            <path d="M8 14.2v5.3M16 14.2v5.3" />
        </template>
        <template v-else-if="name === 'library'">
            <path d="M4 19V6a1 1 0 0 1 1-1h2v15H5a1 1 0 0 1-1-1z" />
            <path d="M9 20V4h3.5v16z" />
            <path d="M14.5 20V8H18a1 1 0 0 1 1 1v11" />
            <path d="M3 20h18" />
        </template>
        <template v-else-if="name === 'archive'">
            <path d="M4 7h16v3H4zM6 10v9h12v-9" />
            <path d="M10 13h4" />
        </template>
        <template v-else-if="name === 'pencil'">
            <path d="M12 20h9" />
            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z" />
        </template>
        <template v-else-if="name === 'trash'">
            <path d="M4 7h16" />
            <path d="M10 11v6M14 11v6" />
            <path d="M6 7V5a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v2" />
            <path d="M9 7V4h6v3" />
            <path d="M7 7l1 12a1 1 0 0 0 1 .9h6a1 1 0 0 0 1-.9l1-12" />
        </template>
        <template v-else-if="name === 'arrows-exchange'">
            <path d="M7 16V4M7 4 3 8M7 4l4 4" />
            <path d="M17 8v12M17 20l4-4M17 20l-4-4" />
        </template>
        <template v-else-if="name === 'eye'">
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" />
            <circle cx="12" cy="12" r="3" />
        </template>
        <template v-else-if="name === 'check'">
            <path d="M5 12.5 10 17.5 19 7" />
        </template>
        <template v-else-if="name === 'x'">
            <path d="M6 6l12 12M18 6 6 18" />
        </template>
        <template v-else-if="name === 'chevron-up'">
            <path d="M6 15l6-6 6 6" />
        </template>
        <template v-else-if="name === 'chevron-down'">
            <path d="M6 9l6 6 6-6" />
        </template>
        <template v-else-if="name === 'copy'">
            <path d="M9 3H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4" />
            <path d="M9 3h6a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z" />
        </template>
        <template v-else-if="name === 'power'">
            <path d="M12 3v9" />
            <path d="M5.64 5.64a9 9 0 1 0 12.72 0" />
        </template>
        <template v-else-if="name === 'send'">
            <path d="M22 2 11 13" />
            <path d="M22 2 15 22l-4-9-9-4z" />
        </template>
        <template v-else-if="name === 'columns'">
            <path d="M9 3v18M15 3v18M3 9h18M3 15h18" />
        </template>
        <template v-else-if="name === 'view-columns'">
            <path d="M4 4h4v16H4zM10 3h4v18h-4zM16 6h4v12h-4z" />
        </template>
        <template v-else-if="name === 'download'">
            <path d="M12 3v12" />
            <path d="M8 11l4 4 4-4" />
            <path d="M4 21h16" />
        </template>
        <template v-else-if="name === 'upload'">
            <path d="M12 21V9" />
            <path d="M8 13l4-4 4 4" />
            <path d="M4 21h16" />
        </template>
        <template v-else-if="name === 'search'">
            <circle cx="11" cy="11" r="6.5" />
            <path d="M16 16l4.5 4.5" />
        </template>
        <template v-else-if="name === 'filter'">
            <path d="M4 6h16" />
            <path d="M7 12h10" />
            <path d="M10 18h4" />
        </template>
        <template v-else-if="name === 'wrench'">
            <path
                d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"
            />
        </template>
        <template v-else-if="name === 'assistant'">
            <!-- Robot / bot — ocupa más el viewBox para legibilidad en FAB -->
            <rect x="5" y="8" width="14" height="11" rx="2.5" />
            <path d="M12 8V5" />
            <circle cx="12" cy="4" r="1.25" fill="currentColor" stroke="none" />
            <path d="M3.5 12.5H5M19 12.5h1.5" />
            <circle cx="9.25" cy="12.25" r="1.1" fill="currentColor" stroke="none" />
            <circle cx="14.75" cy="12.25" r="1.1" fill="currentColor" stroke="none" />
            <path d="M9 16.25h6" />
        </template>
    </svg>
</template>
