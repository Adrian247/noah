<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';

type Field = { key: string; type: string; label: string };
type Section = { title: string; fields: Field[] };

type FormVersion = {
    id: number;
    version: number;
    status: string;
    schema: { sections: Section[] };
};

type FormDef = {
    id: number;
    name: string;
    versions: FormVersion[];
};

const route = useRoute();
const { canWriteModule } = useModuleAccess();
const canWrite = computed(() => canWriteModule('design_forms'));

const form = ref<FormDef | null>(null);
const sections = ref<Section[]>([]);
const loading = ref(true);
const message = ref<string | null>(null);
const saving = ref(false);

const draft = computed(() => form.value?.versions.find((v) => v.status === 'draft'));
const published = computed(() =>
    form.value?.versions
        .filter((v) => v.status === 'published')
        .sort((a, b) => b.version - a.version)[0],
);

async function load() {
    loading.value = true;
    message.value = null;
    try {
        const res = await api<{ data: FormDef }>(`/design/forms/${route.params.id}`);
        form.value = res.data;
        const d = res.data.versions.find((v) => v.status === 'draft');
        sections.value = structuredClone(d?.schema?.sections ?? [{ title: 'Sección 1', fields: [] }]);
    } catch (e) {
        message.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
}

function addSection() {
    sections.value.push({ title: `Sección ${sections.value.length + 1}`, fields: [] });
}

function addField(sectionIndex: number) {
    const key = `campo_${Date.now()}`;
    sections.value[sectionIndex].fields.push({
        key,
        type: 'text',
        label: 'Nuevo campo',
    });
}

function removeField(sectionIndex: number, fieldIndex: number) {
    sections.value[sectionIndex].fields.splice(fieldIndex, 1);
}

async function saveDraft() {
    saving.value = true;
    message.value = null;
    try {
        await api(`/design/forms/${route.params.id}/schema`, {
            method: 'PUT',
            body: JSON.stringify({ schema: { sections: sections.value } }),
        });
        message.value = 'Borrador guardado.';
        await load();
    } catch (e) {
        message.value = (e as Error).message;
    } finally {
        saving.value = false;
    }
}

async function publish() {
    saving.value = true;
    message.value = null;
    try {
        await saveDraft();
        await api(`/design/forms/${route.params.id}/publish`, { method: 'POST' });
        await load();
        const pub = published.value;
        const d = draft.value;
        message.value = pub
            ? `Versión v${pub.version} publicada.${d ? ` Nuevo borrador v${d.version} para siguientes cambios.` : ''}`
            : 'Versión publicada.';
    } catch (e) {
        message.value = (e as Error).message;
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div v-if="loading" class="text-slate-500">Cargando…</div>
    <div v-else-if="form" class="max-w-3xl space-y-4">
        <h2 class="text-xl font-semibold">{{ form.name }}</h2>
        <p class="text-sm text-slate-600">
            <span v-if="published">En producción: v{{ published.version }} (publicada).</span>
            <span v-else class="text-amber-800">Aún no hay versión publicada.</span>
            Borrador de trabajo: v{{ draft?.version ?? '—' }}.
        </p>

        <div v-for="(section, si) in sections" :key="si" class="rounded-lg border bg-white p-4 space-y-3">
            <input
                v-model="section.title"
                class="w-full font-medium border-b border-transparent focus:border-slate-300 outline-none"
            />
            <div
                v-for="(field, fi) in section.fields"
                :key="field.key"
                class="grid gap-2 rounded border border-slate-100 p-2 sm:grid-cols-4"
            >
                <input v-model="field.label" class="rounded border px-2 py-1 text-sm sm:col-span-2" />
                <input v-model="field.key" class="rounded border px-2 py-1 text-sm font-mono text-xs" />
                <select v-model="field.type" class="rounded border px-2 py-1 text-sm">
                    <option value="text">text</option>
                    <option value="textarea">textarea</option>
                    <option value="number">number</option>
                </select>
                <button
                    type="button"
                    class="text-xs text-red-600 sm:col-span-4 text-left"
                    @click="removeField(si, fi)"
                >
                    Quitar campo
                </button>
            </div>
            <button type="button" class="text-sm text-slate-700 underline" @click="addField(si)">
                + Campo
            </button>
        </div>

        <button type="button" class="text-sm underline" @click="addSection">+ Sección</button>

        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                class="rounded-md border px-3 py-2 text-sm"
                :disabled="!canWrite || saving"
                @click="saveDraft"
            >
                Guardar borrador
            </button>
            <button
                type="button"
                class="rounded-md bg-slate-900 px-3 py-2 text-sm text-white"
                :disabled="!canWrite || saving"
                @click="publish"
            >
                Publicar versión
            </button>
        </div>
        <p v-if="!canWrite" class="text-sm text-slate-500">Solo lectura: no puedes editar ni publicar este formulario.</p>
        <p v-if="message" class="text-sm text-slate-600">{{ message }}</p>
    </div>
</template>
