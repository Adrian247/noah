<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '@/api/client';

type FormFieldOption = { key: string; label: string; form_name: string };

type Component = {
    type: string;
    text?: string;
    field?: string;
};

type ReportVersion = {
    id: number;
    version: number;
    status: string;
    components: Component[];
};

type ReportTpl = {
    id: number;
    name: string;
    versions: ReportVersion[];
};

const route = useRoute();
const tpl = ref<ReportTpl | null>(null);
const components = ref<Component[]>([]);
const formFields = ref<FormFieldOption[]>([]);
const loading = ref(true);
const message = ref<string | null>(null);

const draft = computed(() => tpl.value?.versions.find((v) => v.status === 'draft'));
const published = computed(() =>
    tpl.value?.versions
        .filter((v) => v.status === 'published')
        .sort((a, b) => b.version - a.version)[0],
);

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: ReportTpl; form_fields: FormFieldOption[] }>(
            `/design/reports/${route.params.id}`,
        );
        tpl.value = res.data;
        formFields.value = res.form_fields ?? [];
        const d = res.data.versions.find((v) => v.status === 'draft');
        components.value = structuredClone(d?.components ?? []);
    } catch (e) {
        message.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
}

function addTitle() {
    components.value.push({ type: 'title', text: 'Título' });
}

function addParagraph() {
    const first = formFields.value[0];
    components.value.push({ type: 'paragraph', field: first?.key ?? 'corrected_comments' });
}

function removeComponent(index: number) {
    components.value.splice(index, 1);
}

function moveUp(index: number) {
    if (index <= 0) {
        return;
    }
    const copy = [...components.value];
    [copy[index - 1], copy[index]] = [copy[index], copy[index - 1]];
    components.value = copy;
}

function moveDown(index: number) {
    if (index >= components.value.length - 1) {
        return;
    }
    const copy = [...components.value];
    [copy[index], copy[index + 1]] = [copy[index + 1], copy[index]];
    components.value = copy;
}

async function save() {
    await api(`/design/reports/${route.params.id}/components`, {
        method: 'PUT',
        body: JSON.stringify({ components: components.value }),
    });
    message.value = 'Borrador guardado.';
}

async function publish() {
    await save();
    await api(`/design/reports/${route.params.id}/publish`, { method: 'POST' });
    message.value = 'Publicado.';
    await load();
}

onMounted(load);
</script>

<template>
    <div v-if="loading" class="text-slate-500">Cargando…</div>
    <div v-else-if="tpl" class="max-w-2xl space-y-4">
        <h2 class="text-xl font-semibold">{{ tpl.name }}</h2>
        <p class="text-sm text-slate-600">
            <span v-if="published">En producción: v{{ published.version }}.</span>
            Borrador de trabajo: v{{ draft?.version }}.
        </p>

        <ul class="space-y-2">
            <li
                v-for="(c, i) in components"
                :key="i"
                class="rounded border bg-white p-3 text-sm space-y-2"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-mono text-xs text-slate-500">{{ c.type }} #{{ i + 1 }}</span>
                    <div class="flex gap-2 text-xs">
                        <button type="button" class="underline" :disabled="i === 0" @click="moveUp(i)">↑</button>
                        <button
                            type="button"
                            class="underline"
                            :disabled="i === components.length - 1"
                            @click="moveDown(i)"
                        >
                            ↓
                        </button>
                        <button type="button" class="text-red-700 underline" @click="removeComponent(i)">
                            Eliminar
                        </button>
                    </div>
                </div>
                <input
                    v-if="c.type === 'title'"
                    v-model="c.text"
                    class="w-full rounded border px-2 py-1"
                    placeholder="Texto del título"
                />
                <div v-else-if="c.type === 'paragraph'" class="space-y-1">
                    <label class="block text-xs text-slate-500">Campo del formulario / ejecución</label>
                    <select v-model="c.field" class="w-full rounded border px-2 py-1 text-sm">
                        <option v-for="f in formFields" :key="f.key" :value="f.key">
                            {{ f.label }}
                        </option>
                    </select>
                    <p class="text-xs text-slate-400">Clave: <span class="font-mono">{{ c.field }}</span></p>
                </div>
            </li>
        </ul>

        <div class="flex flex-wrap gap-2 text-sm">
            <button type="button" class="underline" @click="addTitle">+ Título</button>
            <button type="button" class="underline" @click="addParagraph">+ Párrafo (campo)</button>
        </div>

        <div class="flex gap-2">
            <button type="button" class="rounded-md border px-3 py-2 text-sm" @click="save">Guardar</button>
            <button type="button" class="rounded-md bg-slate-900 px-3 py-2 text-sm text-white" @click="publish">
                Publicar
            </button>
        </div>
        <p v-if="message" class="text-sm text-slate-600">{{ message }}</p>
    </div>
</template>
