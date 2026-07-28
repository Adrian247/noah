<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useToast } from '@/composables/useToast';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppModal from '@/components/ui/AppModal.vue';
import { FORM_USAGE_OPTIONS, formUsageLabel } from '@/lib/formUsage';

type FormRow = {
    id: number;
    name: string;
    slug: string;
    usage: string;
    usage_label?: string;
    published_version?: { version: number; status: string } | null;
    draft_version?: { version: number; status: string } | null;
};

const { canWriteModule } = useModuleAccess();
const toast = useToast();
const canWrite = computed(() => canWriteModule('design_forms'));

const forms = ref<FormRow[]>([]);
const loading = ref(true);
const name = ref('');
const usage = ref('routine');
const showCreate = ref(false);
const creating = ref(false);

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: FormRow[] }>('/design/forms');
        forms.value = res.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    name.value = '';
    usage.value = 'routine';
    showCreate.value = true;
}

async function createForm() {
    if (!name.value.trim()) {
        toast.warning('Indica el nombre del formulario.');
        return;
    }
    creating.value = true;
    try {
        await api('/design/forms', {
            method: 'POST',
            body: JSON.stringify({ name: name.value.trim(), usage: usage.value }),
        });
        showCreate.value = false;
        toast.success('Formulario creado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        creating.value = false;
    }
}

async function removeForm(row: FormRow) {
    if (!window.confirm(`¿Eliminar el formulario «${row.name}»? Esta acción no se puede deshacer.`)) {
        return;
    }
    try {
        await api(`/design/forms/${row.id}`, { method: 'DELETE' });
        toast.success('Formulario eliminado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

onMounted(load);
</script>

<template>
    <div class="portal-page" data-tour="page-design-forms">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <PageHeader
                class="flex-1"
                title="Formularios"
                subtitle="Tras publicar, la versión queda en producción y se abre un borrador nuevo para el siguiente cambio."
            />
            <div class="flex shrink-0 flex-wrap items-center gap-3">
                <AppButton v-if="canWrite" type="button" @click="openCreate">
                    Nuevo formulario
                </AppButton>
                <RouterLink v-if="canWrite" to="/app/design/forms/settings">
                    <AppButton type="button" variant="secondary">Configuración de campos</AppButton>
                </RouterLink>
            </div>
        </div>

        <AppModal
            :open="showCreate && canWrite"
            title="Nuevo formulario"
            size="sm"
            @close="showCreate = false"
        >
            <form id="create-form-def" class="space-y-4" @submit.prevent="createForm">
                <MaterialField v-model="name" label="Nombre" required />
                <MaterialSelect
                    v-model="usage"
                    label="Uso del formulario"
                    :options="[...FORM_USAGE_OPTIONS]"
                    required
                />
                <p class="text-portal-muted text-xs">
                    El uso no se puede cambiar después. Rutina: tipos de rutina; Equipo/Insumo: fichas en catálogo.
                </p>
            </form>
            <template #footer>
                <button
                    type="button"
                    class="text-portal-muted rounded-xl px-4 py-2 text-sm hover:bg-white/5"
                    @click="showCreate = false"
                >
                    Cancelar
                </button>
                <AppButton type="submit" form="create-form-def" :disabled="creating">
                    {{ creating ? 'Creando…' : 'Crear' }}
                </AppButton>
            </template>
        </AppModal>

        <ReadOnlyNotice v-if="!canWrite" module-label="Formularios" />
        <p v-if="loading" class="text-portal-muted">Cargando…</p>
        <ul v-else class="portal-list-panel divide-y">
            <li v-for="f in forms" :key="f.id" class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                <div class="min-w-0 flex-1">
                    <RouterLink class="text-portal-link font-medium underline" :to="`/app/design/forms/${f.id}`">
                        {{ f.name }}
                    </RouterLink>
                    <p class="text-portal-muted text-xs">{{ f.slug }}</p>
                    <span
                        class="mt-1 inline-block rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-amber-200/90"
                    >
                        {{ f.usage_label ?? formUsageLabel(f.usage) }}
                    </span>
                </div>
                <div class="text-portal-muted shrink-0 text-right text-xs">
                    <p v-if="f.published_version">
                        En uso:
                        <span class="text-portal-heading font-medium"
                            >v{{ f.published_version.version }} publicada</span
                        >
                    </p>
                    <p v-else class="text-amber-500">Sin versión publicada</p>
                    <p v-if="f.draft_version">Borrador: v{{ f.draft_version.version }}</p>
                    <button
                        v-if="canWrite"
                        type="button"
                        class="mt-2 text-xs text-red-400 hover:text-red-300"
                        @click="removeForm(f)"
                    >
                        Eliminar
                    </button>
                </div>
            </li>
        </ul>
    </div>
</template>
