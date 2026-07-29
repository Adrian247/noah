<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import { getToken, getCompanyId } from '@/api/client';

const props = defineProps<{
    routineId: number;
    path: string;
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

    if (!props.routineId || !props.path) {
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

        const url = `/api/v1/routines/${props.routineId}/form-field-media?path=${encodeURIComponent(props.path)}`;
        const res = await fetch(url, { headers });
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
    () => [props.routineId, props.path] as const,
    () => {
        void load();
    },
    { immediate: true },
);

onBeforeUnmount(revoke);
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-white/10 bg-black/20">
        <p v-if="loading" class="text-portal-muted px-3 py-8 text-center text-xs">Cargando imagen…</p>
        <p v-else-if="error" class="portal-msg-warning px-3 py-6 text-center text-xs">
            No se pudo cargar la imagen
        </p>
        <img
            v-else-if="src"
            :src="src"
            :alt="path"
            class="max-h-56 w-full object-contain"
        />
    </div>
</template>
