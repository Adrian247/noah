import { onMounted, ref } from 'vue';
import { api } from '@/api/client';

export type PortalPublicContent = {
    service_title?: string | null;
    service_description?: string | null;
    help_title?: string | null;
    help_text?: string | null;
    contact_email?: string | null;
    contact_phone?: string | null;
    contact_hours?: string | null;
};

const shared = ref<PortalPublicContent | null>(null);
let loadPromise: Promise<void> | null = null;

export function usePortalPublicContent() {
    async function ensureLoaded() {
        if (shared.value !== null) {
            return;
        }
        if (loadPromise) {
            await loadPromise;
            return;
        }
        loadPromise = (async () => {
            try {
                const res = await api<{ data: PortalPublicContent }>('/portal');
                shared.value = res.data;
            } catch {
                shared.value = {};
            }
        })();
        await loadPromise;
    }

    onMounted(() => {
        void ensureLoaded();
    });

    return { content: shared, ensureLoaded };
}
