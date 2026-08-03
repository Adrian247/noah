<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import AppButton from '@/components/ui/AppButton.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';

type VersionOption = { id: number; semver: string; kind: string; notes?: string | null };

const toast = useToast();
const loading = ref(true);
const saving = ref(false);
const allowCollection = ref(false);
const versionId = ref('');
const versions = ref<VersionOption[]>([]);
const legalNotice = ref('');

const versionOptions = () => [
    { value: '', label: 'Versión publicada más reciente (automática)' },
    ...versions.value.map((v) => ({
        value: String(v.id),
        label: `v${v.semver} · ${v.kind}`,
    })),
];

async function load() {
    loading.value = true;
    try {
        const res = await api<{
            data: {
                allow_predictive_training_collection: boolean;
                predictive_algorithm_version_id: number | null;
                available_versions: VersionOption[];
                legal_notice: string;
            };
        }>('/predictive/settings');
        allowCollection.value = res.data.allow_predictive_training_collection;
        versionId.value = res.data.predictive_algorithm_version_id
            ? String(res.data.predictive_algorithm_version_id)
            : '';
        versions.value = res.data.available_versions;
        legalNotice.value = res.data.legal_notice;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    try {
        await api('/predictive/settings', {
            method: 'PUT',
            body: JSON.stringify({
                allow_predictive_training_collection: allowCollection.value,
                predictive_algorithm_version_id: versionId.value ? Number(versionId.value) : null,
            }),
        });
        toast.success('Configuración predictiva guardada.');
        await load();
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
        <p class="text-portal-muted text-sm leading-relaxed">
            El algoritmo predictivo analiza el historial de <strong class="text-portal-heading">rutinas aplicadas</strong>
            a tus activos y clientes según la línea de servicio.
        </p>

        <label class="flex items-start gap-2 text-sm text-portal-heading">
            <input
                v-model="allowCollection"
                type="checkbox"
                class="phoenix-checkbox mt-0.5 rounded"
                :disabled="loading || saving"
            />
            <span>
                Permitir a Phoenix recopilar información de rutinas para entrenamiento
            </span>
        </label>

        <p class="text-portal-muted rounded-xl border border-[color:var(--portal-border)] bg-[color:var(--portal-surface)] p-3 text-xs leading-relaxed">
            {{ legalNotice }}
        </p>

        <MaterialSelect
            v-model="versionId"
            label="Versión del algoritmo predictivo"
            :options="versionOptions()"
            :disabled="loading || saving"
        />

        <AppButton type="button" :disabled="loading || saving" @click="save">
            {{ saving ? 'Guardando…' : 'Guardar' }}
        </AppButton>
    </div>
</template>
