<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import { getToken, getCompanyId } from '@/api/client';

const props = defineProps<{
    evidenceId: number;
    alt?: string | null;
}>();

const src = ref<string | null>(null);
const error = ref(false);
const loading = ref(true);

let objectUrl: string | null = null;

function revoke() {
    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }
}

async function load() {
    revoke();
    src.value = null;
    error.value = false;
    loading.value = true;

    if (!props.evidenceId) {
        loading.value = false;
        error.value = true;
        return;
    }

    try {
        const headers: Record<string, string> = { Accept: 'image/*,application/json' };
        const token = getToken();
        if (token) {
            headers.Authorization = `Bearer ${token}`;
        }
        const companyId = getCompanyId();
        if (companyId) {
            headers['X-Company-Id'] = companyId;
        }

        const res = await fetch(`/api/v1/evidences/${props.evidenceId}/download`, { headers });
        if (!res.ok) {
            throw new Error(String(res.status));
        }
        const blob = await res.blob();
        objectUrl = URL.createObjectURL(blob);
        src.value = objectUrl;
    } catch {
        error.value = true;
    } finally {
        loading.value = false;
    }
}

watch(
    () => props.evidenceId,
    () => {
        void load();
    },
    { immediate: true },
);

onBeforeUnmount(revoke);
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-white/10 bg-black/20">
        <p v-if="loading" class="text-portal-muted px-3 py-10 text-center text-xs">Cargando…</p>
        <p v-else-if="error" class="portal-msg-warning px-3 py-8 text-center text-xs">No se pudo cargar</p>
        <img
            v-else-if="src"
            :src="src"
            :alt="alt ?? `Evidencia ${evidenceId}`"
            class="max-h-48 w-full object-contain"
        />
    </div>
</template>
