<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useToast } from '@/composables/useToast';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import AppButton from '@/components/ui/AppButton.vue';

type FormRow = {
    id: number;
    name: string;
    slug: string;
    published_version?: { version: number; status: string } | null;
    draft_version?: { version: number; status: string } | null;
};

const { canWriteModule } = useModuleAccess();
const toast = useToast();
const canWrite = computed(() => canWriteModule('design_forms'));

const forms = ref<FormRow[]>([]);
const loading = ref(true);
const name = ref('');
const showCreate = ref(false);

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

async function createForm() {
    if (!name.value.trim()) {
        toast.warning('Indica el nombre del formulario.');
        return;
    }
    try {
        await api('/design/forms', {
            method: 'POST',
            body: JSON.stringify({ name: name.value.trim() }),
        });
        name.value = '';
        showCreate.value = false;
        toast.success('Formulario creado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

function openCreate() {
    name.value = '';
    showCreate.value = !showCreate.value;
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
        <form
            v-if="showCreate && canWrite"
            class="portal-form-panel flex max-w-xl flex-wrap items-end gap-4"
            @submit.prevent="createForm"
        >
            <MaterialField v-model="name" label="Nombre del formulario" class="min-w-[14rem] flex-1" required />
            <AppButton type="submit">Crear</AppButton>
            <button type="button" class="text-portal-muted text-sm underline" @click="showCreate = false">
                Cancelar
            </button>
        </form>
        <ReadOnlyNotice v-if="!canWrite" module-label="Formularios" />
        <p v-if="loading" class="text-portal-muted">Cargando…</p>
        <ul v-else class="portal-list-panel divide-y">
            <li v-for="f in forms" :key="f.id" class="flex items-center justify-between px-4 py-3 text-sm">
                <div>
                    <RouterLink class="text-portal-link font-medium underline" :to="`/app/design/forms/${f.id}`">
                        {{ f.name }}
                    </RouterLink>
                    <p class="text-portal-muted text-xs">{{ f.slug }}</p>
                </div>
                <div class="text-portal-muted text-right text-xs">
                    <p v-if="f.published_version">
                        En uso:
                        <span class="text-portal-heading font-medium"
                            >v{{ f.published_version.version }} publicada</span
                        >
                    </p>
                    <p v-else class="text-amber-500">Sin versión publicada</p>
                    <p v-if="f.draft_version">Borrador: v{{ f.draft_version.version }}</p>
                </div>
            </li>
        </ul>
    </div>
</template>
