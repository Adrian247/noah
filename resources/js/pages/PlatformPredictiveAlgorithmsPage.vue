<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppButton from '@/components/ui/AppButton.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import ConfigurableDataTable from '@/components/ui/ConfigurableDataTable.vue';
import type { TableColumnDef } from '@/lib/tableColumns';

type AlgorithmVersion = {
    id: number;
    semver: string;
    status: string;
    kind: string;
    notes: string | null;
    training_summary: {
        companies_opted_in?: number;
        validated_routines?: number;
        assets_covered?: number;
        note?: string;
    } | null;
    published_at: string | null;
    created_at: string | null;
};

const toast = useToast();
const loading = ref(true);
const training = ref(false);
const versions = ref<AlgorithmVersion[]>([]);
const notes = ref('');

const columns: TableColumnDef[] = [
    { id: 'semver', label: 'Versión' },
    { id: 'status', label: 'Estado' },
    { id: 'training', label: 'Entrenamiento' },
    { id: 'notes', label: 'Notas' },
    { id: 'actions', label: 'Acciones', locked: true },
];

function statusBadge(status: string): string {
    if (status === 'published') return 'validated';
    if (status === 'draft') return 'pending_validation';
    return 'inactive';
}

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: AlgorithmVersion[] }>('/platform/predictive/algorithms');
        versions.value = res.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function train() {
    training.value = true;
    try {
        await api('/platform/predictive/algorithms/train', {
            method: 'POST',
            body: JSON.stringify({ notes: notes.value || null }),
        });
        toast.success('Versión draft creada (minor). Revísala y publícala para los clientes.');
        notes.value = '';
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        training.value = false;
    }
}

async function publish(row: AlgorithmVersion) {
    try {
        await api(`/platform/predictive/algorithms/${row.id}/publish`, { method: 'POST' });
        toast.success(`Publicada v${row.semver}. Ya es seleccionable por las empresas.`);
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function archive(row: AlgorithmVersion) {
    try {
        await api(`/platform/predictive/algorithms/${row.id}/archive`, { method: 'POST' });
        toast.success(`Archivada v${row.semver}.`);
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

onMounted(load);
</script>

<template>
    <div class="portal-page">
        <PageHeader
            title="Algoritmo predictivo"
            subtitle="Entrena con el historial de rutinas de empresas con opt-in, versiona con semver y publica para los clientes."
        />

        <section
            class="mb-6 rounded-2xl border border-[color:var(--portal-border)] bg-[color:var(--portal-surface)] p-4"
        >
            <h2 class="text-portal-heading mb-2 text-sm font-semibold uppercase tracking-wide">
                Nuevo entrenamiento
            </h2>
            <p class="text-portal-muted mb-4 text-sm leading-relaxed">
                Solo incluye rutinas validadas de empresas que activaron
                «Permitir a Phoenix recopilar información de rutinas para entrenamiento».
                El corpus no se expone fuera de Phoenix. Queda en borrador hasta que publiques.
                Cada entrenamiento web crea la siguiente versión <strong class="text-portal-heading">minor</strong>.
            </p>
            <div class="grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
                <MaterialField v-model="notes" label="Notas (opcional)" />
                <AppButton type="button" :disabled="training" @click="train">
                    {{ training ? 'Entrenando…' : 'Entrenar nueva versión' }}
                </AppButton>
            </div>
        </section>

        <ConfigurableDataTable
            table-id="platform-predictive-algorithms"
            :columns="columns"
            :rows="versions"
            row-key="id"
            :show-export="false"
            empty-text="Aún no hay versiones. Entrena la primera."
        >
            <template #semver="{ row }">
                <span class="font-mono font-semibold text-portal-heading">v{{ (row as AlgorithmVersion).semver }}</span>
                <p class="text-portal-muted text-xs">{{ (row as AlgorithmVersion).kind }}</p>
            </template>
            <template #status="{ row }">
                <StatusBadge :status="statusBadge((row as AlgorithmVersion).status)" />
                <span class="text-portal-muted ml-1 text-xs">{{ (row as AlgorithmVersion).status }}</span>
            </template>
            <template #training="{ row }">
                <span class="text-sm text-portal-heading">
                    {{ (row as AlgorithmVersion).training_summary?.validated_routines ?? 0 }} rutinas ·
                    {{ (row as AlgorithmVersion).training_summary?.companies_opted_in ?? 0 }} empresas
                </span>
            </template>
            <template #notes="{ row }">
                <span class="text-portal-muted text-sm">{{ (row as AlgorithmVersion).notes || '—' }}</span>
            </template>
            <template #actions="{ row }">
                <div class="flex flex-wrap gap-2" @click.stop>
                    <AppButton
                        v-if="(row as AlgorithmVersion).status === 'draft'"
                        type="button"
                        variant="primary"
                        @click="publish(row as AlgorithmVersion)"
                    >
                        Publicar
                    </AppButton>
                    <AppButton
                        v-if="(row as AlgorithmVersion).status === 'published'"
                        type="button"
                        variant="secondary"
                        @click="archive(row as AlgorithmVersion)"
                    >
                        Archivar
                    </AppButton>
                </div>
            </template>
        </ConfigurableDataTable>
    </div>
</template>
