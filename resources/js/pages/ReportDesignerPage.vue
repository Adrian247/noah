<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '@/api/client';

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
const loading = ref(true);
const message = ref<string | null>(null);

const draft = computed(() => tpl.value?.versions.find((v) => v.status === 'draft'));

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: ReportTpl }>(`/design/reports/${route.params.id}`);
        tpl.value = res.data;
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
    components.value.push({ type: 'paragraph', field: 'corrected_comments' });
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
        <p class="text-sm text-slate-600">Borrador v{{ draft?.version }} ({{ draft?.status }})</p>

        <ul class="space-y-2">
            <li
                v-for="(c, i) in components"
                :key="i"
                class="rounded border bg-white p-3 text-sm space-y-2"
            >
                <span class="font-mono text-xs text-slate-500">{{ c.type }}</span>
                <input
                    v-if="c.type === 'title'"
                    v-model="c.text"
                    class="w-full rounded border px-2 py-1"
                />
                <input
                    v-else-if="c.type === 'paragraph'"
                    v-model="c.field"
                    placeholder="field key"
                    class="w-full rounded border px-2 py-1 font-mono text-xs"
                />
            </li>
        </ul>

        <div class="flex flex-wrap gap-2 text-sm">
            <button type="button" class="underline" @click="addTitle">+ Título</button>
            <button type="button" class="underline" @click="addParagraph">+ Párrafo (campo)</button>
        </div>

        <div class="flex gap-2">
            <button type="button" class="rounded-md border px-3 py-2 text-sm" @click="save">
                Guardar
            </button>
            <button type="button" class="rounded-md bg-slate-900 px-3 py-2 text-sm text-white" @click="publish">
                Publicar
            </button>
        </div>
        <p v-if="message" class="text-sm text-slate-600">{{ message }}</p>
    </div>
</template>
