<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import AppButton from '@/components/ui/AppButton.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';

type VersionOption = {
    id: number;
    semver: string;
    kind: string;
    kind_label?: string;
    notes?: string | null;
    published_at?: string | null;
};

type AlgorithmStatus = {
    kind: string;
    kind_label: string;
    description: string;
    selectable: boolean;
    selection_mode: 'pinned' | 'auto' | 'unavailable';
    selection_hint: string;
    selected_version_id: number | null;
    active_version: VersionOption | null;
    available_versions: VersionOption[];
};

const toast = useToast();
const loading = ref(true);
const saving = ref(false);
const allowCollection = ref(false);
const versionId = ref('');
const algorithms = ref<AlgorithmStatus[]>([]);
const legalNotice = ref('');

const maintenance = computed(
    () => algorithms.value.find((a) => a.selectable) ?? null,
);

const versionOptions = computed(() => {
    const versions = maintenance.value?.available_versions ?? [];
    return [
        { value: '', label: 'Publicada más reciente (automática)' },
        ...versions.map((v) => ({
            value: String(v.id),
            label: `v${v.semver}${v.kind_label ? ` · ${v.kind_label}` : ''}`,
        })),
    ];
});

function modeLabel(mode: AlgorithmStatus['selection_mode']): string {
    if (mode === 'pinned') return 'Fijada';
    if (mode === 'auto') return 'Automática';
    return 'Sin publicar';
}

async function load() {
    loading.value = true;
    try {
        const res = await api<{
            data: {
                allow_predictive_training_collection: boolean;
                predictive_algorithm_version_id: number | null;
                algorithms: AlgorithmStatus[];
                available_versions: VersionOption[];
                legal_notice: string;
            };
        }>('/predictive/settings');
        allowCollection.value = res.data.allow_predictive_training_collection;
        versionId.value = res.data.predictive_algorithm_version_id
            ? String(res.data.predictive_algorithm_version_id)
            : '';
        algorithms.value = res.data.algorithms ?? [];
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
            Phoenix opera
            <strong class="text-portal-heading">tres algoritmos</strong>
            independientes: mantenimiento (equipos), manufactura (demanda de servicios) e inventario
            (demanda de artículos). Solo mantenimiento admite fijar versión; manufactura e inventario
            usan siempre la publicada más reciente.
        </p>

        <label class="flex items-start gap-2 text-sm text-portal-heading">
            <input
                v-model="allowCollection"
                type="checkbox"
                class="phoenix-checkbox mt-0.5 rounded"
                :disabled="loading || saving"
            />
            <span>
                Permitir a Phoenix recopilar información de servicios para entrenamiento
            </span>
        </label>

        <p class="text-portal-muted rounded-xl border border-[color:var(--portal-border)] bg-[color:var(--portal-surface)] p-3 text-xs leading-relaxed">
            {{ legalNotice }}
        </p>

        <div class="space-y-3">
            <h3 class="text-portal-heading text-sm font-semibold">Algoritmos activos</h3>
            <div
                v-for="alg in algorithms"
                :key="alg.kind"
                class="rounded-xl border border-[color:var(--portal-border)] bg-[color:var(--portal-surface)] p-3"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="text-portal-heading text-sm font-medium">{{ alg.kind_label }}</p>
                        <p class="text-portal-muted mt-0.5 text-xs leading-relaxed">{{ alg.description }}</p>
                    </div>
                    <span
                        class="rounded-full border border-[color:var(--portal-border)] px-2 py-0.5 text-[11px] text-portal-heading"
                    >
                        {{ modeLabel(alg.selection_mode) }}
                    </span>
                </div>
                <p class="text-portal-muted mt-2 text-xs">
                    Activa:
                    <template v-if="alg.active_version">
                        <span class="font-mono text-portal-heading">v{{ alg.active_version.semver }}</span>
                    </template>
                    <template v-else>—</template>
                    · {{ alg.selection_hint }}
                </p>

                <MaterialSelect
                    v-if="alg.selectable"
                    v-model="versionId"
                    class="mt-3"
                    label="Versión de mantenimiento"
                    :options="versionOptions"
                    :disabled="loading || saving"
                />
            </div>
        </div>

        <AppButton type="button" :disabled="loading || saving" @click="save">
            {{ saving ? 'Guardando…' : 'Guardar' }}
        </AppButton>
    </div>
</template>
