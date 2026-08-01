<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import { useCompanyStore } from '@/stores/company';
import { useToast } from '@/composables/useToast';
import AppButton from '@/components/ui/AppButton.vue';

const toast = useToast();
const auth = useAuthStore();
const company = useCompanyStore();
const loading = ref(true);
const saving = ref(false);
const aiEnabled = ref(true);

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: { ai_enabled: boolean } }>('/ai/settings');
        aiEnabled.value = res.data.ai_enabled;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    try {
        const res = await api<{ data: { ai_enabled: boolean } }>('/ai/settings', {
            method: 'PUT',
            body: JSON.stringify({ ai_enabled: aiEnabled.value }),
        });
        aiEnabled.value = res.data.ai_enabled;
        if (company.current) {
            company.current.ai_enabled = res.data.ai_enabled;
            const match = auth.companies.find((c) => c.id === company.current?.id);
            if (match) {
                match.ai_enabled = res.data.ai_enabled;
            }
        }
        toast.success('Política de IA actualizada.');
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <p class="text-portal-muted text-sm">
            Controla si los usuarios de esta empresa pueden usar el asistente IA. Los permisos por módulo y rol siguen aplicando.
        </p>
        <label class="flex items-center gap-2 text-sm text-portal-heading">
            <input
                v-model="aiEnabled"
                type="checkbox"
                class="phoenix-checkbox rounded"
                :disabled="loading || saving"
            />
            IA habilitada para usuarios de la empresa
        </label>
        <AppButton type="button" :disabled="loading || saving" @click="save">
            {{ saving ? 'Guardando…' : 'Guardar' }}
        </AppButton>
    </div>
</template>
