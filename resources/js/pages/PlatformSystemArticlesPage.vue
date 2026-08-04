<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppButton from '@/components/ui/AppButton.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';

type SystemArticle = {
    id: number;
    code: string;
    name: string;
    manufacturer?: string | null;
    equipment_type?: { name: string };
};

type ArticleType = { id: number; code: string; name: string };

const toast = useToast();
const articles = ref<SystemArticle[]>([]);
const types = ref<ArticleType[]>([]);
const loading = ref(true);
const form = ref({ code: '', name: '', manufacturer: '', equipment_type_id: '' as string | number });

const typeOptions = () => types.value.map((t) => ({ value: t.id, label: `${t.name} (${t.code})` }));

async function load() {
    loading.value = true;
    try {
        const [articlesRes, typesRes] = await Promise.all([
            api<{ data: SystemArticle[] }>('/platform/catalog/system-articles'),
            api<{ data: ArticleType[] }>('/platform/catalog/system-article-types'),
        ]);
        articles.value = articlesRes.data;
        types.value = typesRes.data;
        if (!form.value.equipment_type_id && types.value[0]) {
            form.value.equipment_type_id = types.value[0].id;
        }
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function create() {
    if (!form.value.code.trim() || !form.value.name.trim()) {
        toast.warning('Código y nombre son obligatorios.');
        return;
    }
    try {
        await api('/platform/catalog/system-articles', {
            method: 'POST',
            body: JSON.stringify({
                equipment_type_id: form.value.equipment_type_id
                    ? Number(form.value.equipment_type_id)
                    : null,
                code: form.value.code.trim(),
                name: form.value.name.trim(),
                manufacturer: form.value.manufacturer || null,
            }),
        });
        form.value = {
            code: '',
            name: '',
            manufacturer: '',
            equipment_type_id: form.value.equipment_type_id,
        };
        await load();
        toast.success('Artículo de sistema creado.');
    } catch (e) {
        toast.error((e as Error).message);
    }
}

onMounted(load);
</script>

<template>
    <div class="portal-page">
        <PageHeader
            title="Artículos de sistema"
            subtitle="Plantillas reutilizables que los tenants pueden importar como clones a su catálogo."
        />

        <div class="portal-list-panel mb-6 space-y-3 p-4">
            <h2 class="text-portal-heading text-sm font-medium">Nuevo artículo de sistema</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <MaterialSelect
                    v-model="form.equipment_type_id"
                    label="Tipo de artículo"
                    :options="typeOptions()"
                />
                <MaterialField v-model="form.code" label="Código" />
                <MaterialField v-model="form.name" label="Nombre" />
                <MaterialField v-model="form.manufacturer" label="Fabricante" />
            </div>
            <AppButton @click="create">Registrar</AppButton>
            <p class="text-portal-muted text-xs">
                Si no hay tipos, se crea automáticamente el tipo «General» en la empresa de catálogo de plataforma.
            </p>
        </div>

        <p v-if="loading" class="text-portal-muted">Cargando…</p>
        <ul v-else class="portal-list-panel divide-y">
            <li v-for="row in articles" :key="row.id" class="px-4 py-3 text-sm">
                <p class="text-portal-heading font-medium">{{ row.code }} · {{ row.name }}</p>
                <p class="text-portal-muted text-xs">
                    {{ row.equipment_type?.name ?? 'Sin tipo' }}
                    <span v-if="row.manufacturer"> · {{ row.manufacturer }}</span>
                </p>
            </li>
            <li v-if="articles.length === 0" class="text-portal-muted px-4 py-6">Sin artículos de sistema.</li>
        </ul>
    </div>
</template>
